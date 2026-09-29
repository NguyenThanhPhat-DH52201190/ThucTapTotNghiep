<?php

namespace App\Http\Controllers;

use App\Services\AuditTrailService;
use App\Services\OrderMaterialRequirementService;
use App\Services\StockRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockRecordController extends Controller
{
    public function index(Request $request)
    {
        $balances = DB::table('inventory_balances')->select('material_id')
            ->selectRaw('SUM(balance_qty) as on_hand, SUM(reserved_qty) as reserved')->groupBy('material_id');
        $records = DB::table('stock_records as record')->join('materials', 'materials.id', '=', 'record.material_id')
            ->leftJoinSub($balances, 'stock', 'stock.material_id', '=', 'record.material_id')
            ->select('record.*', 'materials.internal_code', 'materials.material_name', 'materials.unit', 'materials.color', 'materials.size')
            ->selectRaw('COALESCE(stock.on_hand, 0) as on_hand, COALESCE(stock.reserved, 0) as reserved')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->q) . '%';
                $q->where(fn ($q) => $q->where('materials.internal_code', 'like', $term)->orWhere('materials.material_name', 'like', $term));
            })->orderBy('record.sort_order')->orderBy('materials.internal_code')->paginate(30)->withQueryString();
        return view('admin.stock-records.index', compact('records'));
    }

    public function sync(Request $request, StockRecordService $service, AuditTrailService $audit)
    {
        $added = $service->importInventory();
        $audit->record('stock_records_initialized', 'stock_record', null, $request->user()->id, [], ['added' => $added]);
        return back()->with('success', "$added new material records added. Existing opening balances were preserved.");
    }

    public function position(Request $request, int $id, AuditTrailService $audit)
    {
        $data = $request->validate(['sort_order' => 'required|integer|min:1|max:1000000']);
        DB::transaction(function () use ($id, $data, $request, $audit) {
            $record = DB::table('stock_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            DB::table('stock_records')->where('id', $id)->update($data + ['updated_at' => now()]);
            $audit->record('stock_record_reordered', 'stock_record', $id, $request->user()->id, ['sort_order' => $record->sort_order], $data);
        });
        return back()->with('success', 'Material order saved.');
    }

    public function show(Request $request, int $id, StockRecordService $service, OrderMaterialRequirementService $requirements)
    {
        $record = DB::table('stock_records')->find($id);
        abort_unless($record, 404);
        $material = DB::table('materials')->find($record->material_id);
        abort_unless($material, 404);
        $balances = DB::table('inventory_balances as balance')->leftJoin('warehouses', 'warehouses.id', '=', 'balance.warehouse_id')
            ->where('balance.material_id', $material->id)->select('balance.*', 'warehouses.name as warehouse_name')->orderBy('balance.id')->get();
        $purchaseItems = collect();
        if (Schema::hasTable('purchase_orders') && Schema::hasTable('po_items') && Schema::hasColumn('po_items', 'material_id')) {
            $purchaseItems = DB::table('po_items as item')->join('purchase_orders as po', 'po.id', '=', 'item.po_id')
                ->where(function ($query) use ($material) {
                    $query->where('item.material_id', $material->id)
                        ->orWhere('item.material_code', $material->internal_code);
                })
                ->select('po.id as po_id', 'po.po_number', 'po.status as po_status', 'po.order_date', 'po.expected_delivery',
                    'item.id as po_item_id', 'item.material_code', 'item.material_name', 'item.unit', 'item.quantity',
                    'item.received_qty', 'item.expected_date', 'item.status as item_status')
                ->orderByDesc('po.order_date')->orderByDesc('po.id')->orderBy('item.id')->get();
        }
        $plan = $service->plan($record, $requirements);
        $movements = DB::table('inventory_transactions')->where('material_id', $material->id);
        $ledgerQty = (float) $record->opening_qty + (float) (clone $movements)->where('id', '>', $record->opening_transaction_id)->sum('quantity');
        $historyOpening = (float) $record->opening_qty;
        if ($request->input('history') === 'all') {
            $historyOpening -= (float) (clone $movements)->where('id', '<=', $record->opening_transaction_id)->sum('quantity');
        } else {
            $movements->where('id', '>', $record->opening_transaction_id);
        }
        $transactions = (clone $movements)->orderBy('id')->paginate(50, ['*'], 'history_page')->withQueryString();
        $running = $historyOpening;
        if ($transactions->count()) {
            $running += (float) (clone $movements)->where('id', '<', $transactions->first()->id)->sum('quantity');
        }
        foreach ($transactions as $transaction) {
            $running += (float) $transaction->quantity;
            $transaction->running_qty = $running;
        }
        $actors = DB::table('users')->whereIn('id', $transactions->pluck('created_by')->filter())->pluck('name', 'id');
        $issueOrders = DB::table('material_issues as issue')->join('material_requisitions as req', 'req.id', '=', 'issue.requisition_id')
            ->join('ocs', 'ocs.id', '=', 'req.cutsheet_id')->whereIn('issue.id', $transactions->where('reference_type', 'MATERIAL_ISSUE')->pluck('reference_id'))
            ->pluck('ocs.CS', 'issue.id');
        $issueTransactionIds = $transactions->where('reference_type', 'MATERIAL_ISSUE')->where('transaction_type', 'OUT')
            ->where('quantity', '<', 0)->pluck('id');
        $linkedItems = Schema::hasTable('issue_items') && Schema::hasColumn('issue_items', 'inventory_transaction_id')
            ? DB::table('issue_items')->whereIn('inventory_transaction_id', $issueTransactionIds)->where('status', 'issued')->get()->keyBy('inventory_transaction_id')
            : collect();
        $activeIssues = DB::table('material_issues')->whereIn('id', $transactions->where('reference_type', 'MATERIAL_ISSUE')->pluck('reference_id'))
            ->where('status', 'issued')->pluck('id')->flip();
        $editableIssueTransactions = $linkedItems->filter(function ($item, $txId) use ($transactions, $activeIssues) {
            $transaction = $transactions->firstWhere('id', $txId);
            // Split allocations on a Delivery Bill cannot be edited as one stock line.
            return $transaction && abs(abs((float) $transaction->quantity) - (float) $item->issued_qty) < 0.00001
                && isset($activeIssues[$transaction->reference_id]);
        })->keys()->flip();
        $boms = DB::table('bom_headers')->where('bom_kind', 'template')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('bom_items')->whereColumn('bom_items.bom_header_id', 'bom_headers.id')->where('material_id', $material->id))
            ->orderBy('style_no')->get();
        return view('admin.stock-records.show', compact('record', 'material', 'balances', 'purchaseItems', 'plan', 'transactions', 'ledgerQty', 'historyOpening', 'actors', 'issueOrders', 'boms', 'editableIssueTransactions'));
    }

    private function editableIssueTransaction(int $recordId, int $transactionId, bool $lock = false): array
    {
        $record = DB::table('stock_records')->find($recordId);
        abort_unless($record, 404);
        $query = DB::table('inventory_transactions')->where('id', $transactionId)
            ->where('material_id', $record->material_id)->where('reference_type', 'MATERIAL_ISSUE')
            ->where('transaction_type', 'OUT')->where('quantity', '<', 0);
        if ($lock) $query->lockForUpdate();
        $transaction = $query->first();
        abort_unless($transaction, 404);

        $issueQuery = DB::table('material_issues')->where('id', $transaction->reference_id);
        if ($lock) $issueQuery->lockForUpdate();
        $issue = $issueQuery->first();
        abort_unless($issue && $issue->status === 'issued', 404);
        $itemQuery = DB::table('issue_items')->where('inventory_transaction_id', $transactionId)->where('issue_id', $issue->id)->where('status', 'issued');
        if ($lock) $itemQuery->lockForUpdate();
        $item = $itemQuery->first();
        abort_unless($item, 404);
        abort_unless(abs(abs((float) $transaction->quantity) - (float) $item->issued_qty) < 0.00001, 422,
            'This issue line was split across multiple stock allocations and cannot be changed as a single line.');

        return [$record, $transaction, $issue, $item];
    }

    private function matchingBalance(object $transaction, bool $lock = false): object
    {
        $query = DB::table('inventory_balances')->where('material_id', $transaction->material_id);
        foreach ([
            'warehouse_id' => $transaction->from_warehouse_id,
            'location_id' => $transaction->location_id,
            'material_color' => $transaction->material_color,
            'material_size' => $transaction->material_size,
            'lot_roll_no' => $transaction->lot_roll_no,
            'lot_no' => $transaction->lot_no,
            'roll_no' => $transaction->roll_no,
            'location' => $transaction->location,
        ] as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }
        if ($lock) $query->lockForUpdate();
        return $query->firstOrFail();
    }

    public function editIssue(Request $request, int $id, int $transactionId)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'warehouse'], true), 403);
        [, $transaction, $issue, $item] = $this->editableIssueTransaction($id, $transactionId);
        $material = DB::table('materials')->find($transaction->material_id);
        $deliveryBill = DB::table('delivery_bills')->where('issue_id', $issue->id)->first();
        return view('admin.stock-records.edit-issue', compact('transaction', 'issue', 'item', 'material', 'deliveryBill', 'id'));
    }

    public function updateIssue(Request $request, int $id, int $transactionId, AuditTrailService $audit)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'warehouse'], true), 403);
        $data = $request->validate([
            'issue_date' => 'required|date', 'quantity' => 'required|numeric|gt:0|max:99999999|decimal:0,4',
            'notes' => 'nullable|string|max:2000', 'reason' => 'required|string|max:1000',
        ]);
        try {
            $newStatus = DB::transaction(function () use ($request, $id, $transactionId, $data, $audit) {
                [, $transaction, $issue, $issueItem] = $this->editableIssueTransaction($id, $transactionId, true);
                $balance = $this->matchingBalance($transaction, true);
                $reqItem = DB::table('requisition_items')->where('id', $issueItem->requisition_item_id)->lockForUpdate()->firstOrFail();
                $requisition = DB::table('material_requisitions')->where('id', $reqItem->requisition_id)->lockForUpdate()->firstOrFail();
                if ($requisition->status === 'cancelled') {
                    throw ValidationException::withMessages(['quantity' => 'A cancelled requisition cannot be edited. Void this issue instead.']);
                }

                $oldQty = abs((float) $transaction->quantity);
                $newQty = (float) $data['quantity'];
                $updatedIssued = (float) $reqItem->issued_qty - $oldQty + $newQty;
                $tolerance = max(0, (float) env('MAX_ISSUE_TOLERANCE_PERCENT', 0)) / 100;
                if ($updatedIssued > (float) $reqItem->requested_qty * (1 + $tolerance) + 0.00001) {
                    throw ValidationException::withMessages(['quantity' => 'Total issued quantity would exceed the requisition allowance.']);
                }

                $reservation = DB::table('inventory_reservations')->where('requisition_item_id', $reqItem->id)
                    ->where('inventory_balance_id', $balance->id)->lockForUpdate()->first();
                $restoredReservation = $reservation && in_array($reservation->status, ['active', 'consumed'], true);
                $balanceQty = (float) $balance->balance_qty + $oldQty;
                $reservedQty = (float) $balance->reserved_qty;
                $consumedQty = (float) ($reservation->consumed_qty ?? 0);
                if ($restoredReservation) {
                    if ($consumedQty + 0.00001 < $oldQty) throw new \RuntimeException('Reservation history does not match this issue. Contact an administrator.');
                    $consumedQty -= $oldQty;
                    $reservedQty += $oldQty;
                }

                $reservedTake = 0.0;
                if ($restoredReservation) $reservedTake = min($newQty, max(0, (float) $reservation->reserved_qty - $consumedQty));
                $freeTake = $newQty - $reservedTake;
                if ($freeTake > $balanceQty - $reservedQty + 0.00001) {
                    throw ValidationException::withMessages(['quantity' => 'The selected lot does not have enough available stock for this quantity.']);
                }
                $balanceQty -= $newQty;
                $reservedQty -= $reservedTake;
                $consumedQty += $reservedTake;
                DB::table('inventory_balances')->where('id', $balance->id)->update([
                    'balance_qty' => $balanceQty, 'reserved_qty' => $reservedQty, 'updated_at' => now(),
                ]);
                if ($restoredReservation) {
                    DB::table('inventory_reservations')->where('id', $reservation->id)->update([
                        'consumed_qty' => $consumedQty,
                        'status' => $consumedQty + 0.00001 >= (float) $reservation->reserved_qty ? 'consumed' : 'active',
                        'updated_at' => now(),
                    ]);
                } elseif ($reservation && $oldQty > 0) {
                    DB::table('inventory_reservations')->where('id', $reservation->id)->update([
                        'consumed_qty' => max(0, $consumedQty - $oldQty), 'updated_at' => now(),
                    ]);
                }

                DB::table('requisition_items')->where('id', $reqItem->id)->update(['issued_qty' => $updatedIssued, 'updated_at' => now()]);
                DB::table('issue_items')->where('id', $issueItem->id)->update(['issued_qty' => $newQty, 'updated_at' => now()]);
                DB::table('inventory_transactions')->where('reference_type', 'MATERIAL_ISSUE')->where('reference_id', $issue->id)
                    ->update(['transaction_date' => $data['issue_date'], 'updated_at' => now()]);
                DB::table('inventory_transactions')->where('id', $transactionId)->update([
                    'quantity' => -$newQty, 'transaction_date' => $data['issue_date'], 'notes' => $data['notes'] ?? null,
                    'unit_cost' => $balance->unit_cost, 'total_cost' => -$newQty * (float) $balance->unit_cost, 'updated_at' => now(),
                ]);
                DB::table('material_issues')->where('id', $issue->id)->update(['issue_date' => $data['issue_date'], 'updated_at' => now()]);
                $this->reviseDeliveryBill($issue->id, $balance, $oldQty, $newQty, $data['issue_date'], $data['reason'], $request->user()->id, $audit);

                $this->refreshRequisitionStatus((int) $requisition->id);
                $audit->record('material_issue_line_updated', 'material_issue', (int) $issue->id, $request->user()->id,
                    ['transaction' => (array) $transaction, 'issue_item' => (array) $issueItem],
                    ['transaction_id' => $transactionId, 'quantity' => $newQty, 'issue_date' => $data['issue_date'], 'notes' => $data['notes'] ?? null], $data['reason']);
                return [
                    'status' => DB::table('material_requisitions')->where('id', $requisition->id)->value('status'),
                    'requisition_id' => (int) $requisition->id,
                ];
            });
            if ($newStatus['status'] === 'completed') {
                app(\App\Services\OutboxService::class)->record('material_requisition_completed', 'material_requisition', $newStatus['requisition_id'], [
                    'event' => 'material_requisition_completed', 'requisition_id' => $newStatus['requisition_id'],
                ]);
            }
            return redirect()->route('admin.stock-records.show', $id)->with('success', 'Issue line updated and stock recalculated.');
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException) throw $e;
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function voidIssue(Request $request, int $id, int $transactionId, AuditTrailService $audit)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'warehouse'], true), 403);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        try {
            DB::transaction(function () use ($request, $id, $transactionId, $data, $audit) {
                [, $transaction, $issue, $issueItem] = $this->editableIssueTransaction($id, $transactionId, true);
                $balance = $this->matchingBalance($transaction, true);
                $reqItem = DB::table('requisition_items')->where('id', $issueItem->requisition_item_id)->lockForUpdate()->firstOrFail();
                $requisition = DB::table('material_requisitions')->where('id', $reqItem->requisition_id)->lockForUpdate()->firstOrFail();
                $qty = abs((float) $transaction->quantity);
                $reservation = DB::table('inventory_reservations')->where('requisition_item_id', $reqItem->id)
                    ->where('inventory_balance_id', $balance->id)->lockForUpdate()->first();
                $restoreReserve = $reservation && in_array($reservation->status, ['active', 'consumed'], true)
                    && (float) $reservation->consumed_qty + 0.00001 >= $qty;
                DB::table('inventory_balances')->where('id', $balance->id)->update([
                    'balance_qty' => (float) $balance->balance_qty + $qty,
                    'reserved_qty' => (float) $balance->reserved_qty + ($restoreReserve ? $qty : 0), 'updated_at' => now(),
                ]);
                if ($reservation) {
                    $consumed = max(0, (float) $reservation->consumed_qty - $qty);
                    DB::table('inventory_reservations')->where('id', $reservation->id)->update([
                        'consumed_qty' => $consumed,
                        'status' => $restoreReserve ? ($consumed + 0.00001 >= (float) $reservation->reserved_qty ? 'consumed' : 'active') : $reservation->status,
                        'updated_at' => now(),
                    ]);
                }
                DB::table('requisition_items')->where('id', $reqItem->id)->update([
                    'issued_qty' => max(0, (float) $reqItem->issued_qty - $qty), 'updated_at' => now(),
                ]);
                DB::table('issue_items')->where('id', $issueItem->id)->update(['issued_qty' => 0, 'status' => 'voided', 'updated_at' => now()]);
                $reversal = (array) $transaction;
                unset($reversal['id']);
                $reversal = array_merge($reversal, [
                    'transaction_type' => 'IN', 'reference_type' => 'MATERIAL_ISSUE_REVERSAL',
                    'reference_doc' => ($transaction->reference_doc ?: 'ISSUE-'.$issue->id).'-VOID',
                    'quantity' => $qty, 'total_cost' => $qty * (float) $transaction->unit_cost,
                    'notes' => 'Reversal of transaction #'.$transactionId.'. Reason: '.$data['reason'],
                    'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('inventory_transactions')->insert($reversal);
                $this->reviseDeliveryBill($issue->id, $balance, $qty, null, $issue->issue_date, $data['reason'], $request->user()->id, $audit);
                $activeItems = DB::table('issue_items')->where('issue_id', $issue->id)->where('status', 'issued')->where('issued_qty', '>', 0)->exists();
                if (!$activeItems) DB::table('material_issues')->where('id', $issue->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                $this->refreshRequisitionStatus((int) $requisition->id);
                $audit->record('material_issue_line_voided', 'material_issue', (int) $issue->id, $request->user()->id,
                    ['transaction' => (array) $transaction, 'issue_item' => (array) $issueItem],
                    ['transaction_id' => $transactionId, 'returned_quantity' => $qty], $data['reason']);
            });
            return redirect()->route('admin.stock-records.show', $id)->with('success', 'Issue line voided. Stock was returned and the original record was retained.');
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException) throw $e;
            return back()->with('error', $e->getMessage());
        }
    }

    private function refreshRequisitionStatus(int $requisitionId): void
    {
        $requisition = DB::table('material_requisitions')->where('id', $requisitionId)->lockForUpdate()->first();
        if (!$requisition || $requisition->status === 'cancelled') return;
        $items = DB::table('requisition_items')->where('requisition_id', $requisitionId)->get();
        $issued = (float) $items->sum('issued_qty');
        $required = (float) $items->sum('requested_qty');
        $status = $issued <= 0 ? 'pending' : ($issued + 0.00001 >= $required ? 'completed' : 'partial');
        DB::table('material_requisitions')->where('id', $requisitionId)->update(['status' => $status, 'updated_at' => now()]);
    }

    private function reviseDeliveryBill(int $issueId, object $balance, float $oldQty, ?float $newQty, string $date, string $reason, int $userId, AuditTrailService $audit): void
    {
        $bill = DB::table('delivery_bills')->where('issue_id', $issueId)->lockForUpdate()->first();
        if (!$bill) return;

        $lines = json_decode($bill->lines, true) ?: [];
        $matches = collect($lines)->filter(fn ($line) => (int) ($line['balance_id'] ?? 0) === (int) $balance->id
            && abs((float) ($line['quantity'] ?? 0) - $oldQty) < 0.00001)->keys()->values();
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['quantity' => 'Could not uniquely match this stock line to the Delivery Bill. No changes were saved.']);
        }

        $lineIndex = $matches->first();
        if ($newQty === null) array_splice($lines, $lineIndex, 1);
        else $lines[$lineIndex]['quantity'] = $newQty;

        $header = json_decode($bill->header, true) ?: [];
        $revisionPath = 'delivery-bills/'.Str::uuid().'.xlsx';
        app(\App\Services\DeliveryBillExcelService::class)->write($header, $lines, $date, $revisionPath);
        DB::table('delivery_bills')->where('id', $bill->id)->update([
            'lines' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'issued_on' => $date,
            'file_path' => $revisionPath, 'updated_at' => now(),
        ]);
        $audit->record('delivery_bill_revised_from_stock', 'delivery_bill', (int) $bill->id, $userId,
            (array) $bill, ['lines' => $lines, 'issued_on' => $date, 'file_path' => $revisionPath], $reason);
    }

    public function priorities(Request $request, int $id, StockRecordService $service, AuditTrailService $audit)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:0', 'priorities' => 'required|array|min:1',
            'priorities.*.cutsheet_id' => 'required|integer|distinct',
            'priorities.*.sort_order' => 'required|integer|min:1|max:1000000',
        ]);
        DB::transaction(function () use ($data, $id, $request, $service, $audit) {
            $record = DB::table('stock_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            if ((int) $record->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['priorities' => 'Priorities changed in another session. Reload this page before saving.']);
            }
            $allowed = $service->activeOrders($record->material_id)->pluck('id');
            if (collect($data['priorities'])->pluck('cutsheet_id')->diff($allowed)->isNotEmpty()) {
                throw ValidationException::withMessages(['priorities' => 'An order no longer uses this material or is no longer active. Reload this page.']);
            }
            $before = DB::table('stock_record_priorities')->where('stock_record_id', $id)->get()->toArray();
            foreach ($data['priorities'] as $entry) {
                DB::table('stock_record_priorities')->updateOrInsert(['stock_record_id' => $id, 'cutsheet_id' => $entry['cutsheet_id']],
                    ['sort_order' => $entry['sort_order'], 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('stock_records')->where('id', $id)->increment('revision', 1, ['updated_at' => now()]);
            $audit->record('stock_priorities_changed', 'stock_record', $id, $request->user()->id, $before, $data['priorities']);
        });
        return back()->with('success', 'Priorities saved and projected stock recalculated. Actual inventory was not changed.');
    }
}
