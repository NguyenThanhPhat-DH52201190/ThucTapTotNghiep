<?php

namespace App\Http\Controllers;

use App\Services\InventoryLedgerService;
use App\Services\OutboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    /** Warehouse execution queue: requisitions awaiting issue and issue history. */
    public function requisitions(Request $request)
    {
        $requisitions = DB::table('material_requisitions')
            ->join('ocs', 'material_requisitions.cutsheet_id', '=', 'ocs.id')
            ->select('material_requisitions.*', 'ocs.CS', 'ocs.SNo', 'ocs.Sname', 'ocs.Color as order_color')
            ->when($request->filled('status'), fn ($query) => $query->where('material_requisitions.status', $request->status))
            ->orderByRaw("FIELD(material_requisitions.status, 'pending', 'partial', 'completed', 'cancelled')")
            ->orderByDesc('material_requisitions.created_at')
            ->get();

        $itemsByRequisition = DB::table('requisition_items')
            ->join('materials', 'requisition_items.material_id', '=', 'materials.id')
            ->whereIn('requisition_items.requisition_id', $requisitions->pluck('id'))
            ->select('requisition_items.*', 'materials.internal_code', 'materials.material_name', 'materials.unit')
            ->orderBy('materials.internal_code')->get()->groupBy('requisition_id');

        $balancesByMaterial = DB::table('inventory_balances')
            ->join('materials', 'inventory_balances.material_id', '=', 'materials.id')
            ->leftJoin('locations', 'inventory_balances.location_id', '=', 'locations.id')
            ->whereRaw('inventory_balances.balance_qty > inventory_balances.reserved_qty')
            ->select('inventory_balances.*', 'materials.internal_code', 'materials.unit', 'locations.location_code')
            ->orderBy('materials.internal_code')->orderBy('inventory_balances.lot_roll_no')
            ->get()->groupBy('material_id');

        $issues = DB::table('material_issues')
            ->join('material_requisitions', 'material_issues.requisition_id', '=', 'material_requisitions.id')
            ->join('ocs', 'material_requisitions.cutsheet_id', '=', 'ocs.id')
            ->select('material_issues.*', 'material_requisitions.requisition_code', 'ocs.CS')
            ->orderByDesc('material_issues.issue_date')->orderByDesc('material_issues.id')->limit(30)->get();

        return view('admin.inventory.requisitions', compact('requisitions', 'itemsByRequisition', 'balancesByMaterial', 'issues'));
    }

    public function stockCounts()
    {
        $counts = DB::table('stock_counts')->orderByDesc('id')->paginate(20);
        $balances = DB::table('inventory_balances')->join('materials', 'inventory_balances.material_id', '=', 'materials.id')
            ->select('inventory_balances.*', 'materials.internal_code')->where('balance_qty', '>', 0)->orderBy('materials.internal_code')->get();
        return view('admin.inventory.stock-counts', compact('counts', 'balances'));
    }

    public function storeStockCount(Request $request)
    {
        $data = $request->validate(['notes' => 'nullable|string', 'items' => 'required|array|min:1', 'items.*.balance_id' => 'required|exists:inventory_balances,id', 'items.*.counted_qty' => 'required|numeric|min:0', 'items.*.reason' => 'nullable|string']);
        DB::transaction(function () use ($data, $request) {
            $id = DB::table('stock_counts')->insertGetId(['count_code' => 'CNT-'.now()->format('YmdHisv'), 'status' => 'submitted', 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['items'] as $entry) {
                $balance = DB::table('inventory_balances')->where('id', $entry['balance_id'])->lockForUpdate()->firstOrFail();
                DB::table('stock_count_items')->insert(['stock_count_id' => $id, 'inventory_balance_id' => $balance->id, 'system_qty' => $balance->balance_qty, 'counted_qty' => $entry['counted_qty'], 'reason' => $entry['reason'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        return back()->with('success', 'Stock count submitted for approval.');
    }

    public function approveStockCount(int $id, InventoryLedgerService $ledger, Request $request)
    {
        try {
            DB::transaction(function () use ($id, $ledger, $request) {
                $count = DB::table('stock_counts')->where('id', $id)->lockForUpdate()->firstOrFail();
                if ($count->status !== 'submitted') throw new \RuntimeException('Only submitted counts can be approved.');
                foreach (DB::table('stock_count_items')->where('stock_count_id', $id)->get() as $item) {
                    if ((float)$item->system_qty !== (float)$item->counted_qty) $ledger->adjust(['balance_id' => $item->inventory_balance_id, 'new_qty' => $item->counted_qty, 'reason' => 'Stock count '.$count->count_code.': '.($item->reason ?: 'approved variance'), 'user_id' => $request->user()->id]);
                }
                DB::table('stock_counts')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'updated_at' => now()]);
            });
            return back()->with('success', 'Stock count approved and variances posted to the ledger.');
        } catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
    }
    public function cancelRequisition(Request $request, int $id, InventoryLedgerService $ledger)
    {
        try {
            DB::transaction(function () use ($id, $request, $ledger) {
                $requisition = DB::table('material_requisitions')->where('id', $id)->lockForUpdate()->first();
                if (!$requisition || !in_array($requisition->status, ['pending', 'partial'], true)) {
                    throw new \RuntimeException('Only pending or partially issued requisitions can be cancelled.');
                }
                $ledger->releaseForRequisition($id, $request->user()?->id);
                DB::table('material_requisitions')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            });
            return back()->with('success', 'Requisition cancelled and remaining stock was unreserved.');
        } catch (\Throwable $e) {
            Log::warning('Requisition cancellation rejected', ['requisition_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function issue(Request $request, InventoryLedgerService $ledger, \App\Services\AuditTrailService $audit)
    {
        $request->validate([
            'requisition_id' => 'required|exists:material_requisitions,id',
            'receiver_name' => 'nullable|string|max:191',
            'items' => 'required|array|min:1',
            'items.*.requisition_item_id' => 'required|exists:requisition_items,id',
            'items.*.balance_id' => 'required|exists:inventory_balances,id',
            'items.*.quantity' => 'required|numeric|gt:0',
        ]);

        try {
            DB::transaction(function () use ($request, $ledger) {
                $issueCode = 'ISS-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(6));
                $issueId = DB::table('material_issues')->insertGetId([
                    'issue_code' => $issueCode, 'requisition_id' => $request->requisition_id,
                    'issue_date' => today(), 'receiver_name' => $request->receiver_name, 'status' => 'issued',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($request->items as $entry) {
                    $balance = DB::table('inventory_balances')->where('id', $entry['balance_id'])->lockForUpdate()->first();
                    $reqItem = DB::table('requisition_items')->where('id', $entry['requisition_item_id'])->lockForUpdate()->first();
                    if (!$balance || !$reqItem || $reqItem->requisition_id !== (int) $request->requisition_id || $balance->material_id !== $reqItem->material_id) {
                        throw new \RuntimeException('Invalid material issue item.');
                    }
                    if (($reqItem->material_color !== null && $balance->material_color !== $reqItem->material_color)
                        || ($reqItem->material_size !== null && $balance->material_size !== $reqItem->material_size)) {
                        throw new \RuntimeException('Selected lot does not match the required material color or size.');
                    }
                    $tolerance = max(0, (float) env('MAX_ISSUE_TOLERANCE_PERCENT', 0)) / 100;
                    $remainingAllowed = ($reqItem->requested_qty * (1 + $tolerance)) - $reqItem->issued_qty;
                    if ($entry['quantity'] > $remainingAllowed) throw new \RuntimeException('Issued quantity exceeds the permitted tolerance.');
                    $material = DB::table('materials')->find($balance->material_id);
                    $ledger->issue([
                        'balance_id' => $balance->id, 'issue_id' => $issueId, 'issue_code' => $issueCode,
                        'issue_date' => today(), 'material_code' => $material->internal_code, 'unit' => $material->unit,
                        'quantity' => $entry['quantity'], 'requisition_item_id' => $reqItem->id,
                        'user_id' => $request->user()?->id,
                    ]);
                    DB::table('issue_items')->insert([
                        'issue_id' => $issueId, 'requisition_item_id' => $reqItem->id, 'material_id' => $balance->material_id,
                        'material_color' => $balance->material_color, 'material_size' => $balance->material_size,
                        'lot_roll_no' => $balance->lot_roll_no, 'location' => $balance->location, 'location_id' => $balance->location_id,
                        'issued_qty' => $entry['quantity'], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('requisition_items')->where('id', $reqItem->id)->update([
                        'issued_qty' => $reqItem->issued_qty + $entry['quantity'], 'updated_at' => now(),
                    ]);
                }
                $unissued = DB::table('requisition_items')->where('requisition_id', $request->requisition_id)
                    ->whereColumn('issued_qty', '<', 'requested_qty')->exists();
                DB::table('material_requisitions')->where('id', $request->requisition_id)->update([
                    'status' => $unissued ? 'partial' : 'completed', 'updated_at' => now(),
                ]);
            });
            $status = DB::table('material_requisitions')->where('id', $request->requisition_id)->value('status');
            if ($status === 'completed') {
                app(OutboxService::class)->record('material_requisition_completed', 'material_requisition', (int) $request->requisition_id, [
                    'event' => 'material_requisition_completed', 'requisition_id' => (int) $request->requisition_id,
                ]);
            }
            $audit->record('material_issued', 'material_requisition', (int) $request->requisition_id, $request->user()?->id, [], ['items' => $request->items, 'status' => $status], $request->receiver_name ?: 'Material issue posted');
            return back()->with('success', 'Material issue posted through the inventory ledger.');
        } catch (\Throwable $e) {
            Log::warning('Material issue rejected', ['message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
    // ============ WAREHOUSES ============
    public function warehouses()
    {
        $warehouses = DB::table('warehouses')->orderBy('name')->get();
        $locations = DB::table('locations')->join('warehouses', 'locations.warehouse_id', '=', 'warehouses.id')
            ->select('locations.*', 'warehouses.code as warehouse_code')->orderBy('warehouses.code')->orderBy('locations.location_code')->get();
        return view('admin.inventory.warehouses', compact('warehouses', 'locations'));
    }

    public function warehousesStore(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:warehouses,code',
            'name' => 'required',
            'type' => 'required|in:raw_material,wip,finished_goods',
        ]);

        DB::table('warehouses')->insert([
            'code' => $request->code,
            'name' => $request->name,
            'location' => $request->location,
            'type' => $request->type,
            'notes' => $request->notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Warehouse created');
    }

    public function warehouseUpdate(Request $request, int $id)
    {
        $data = $request->validate(['code' => 'required|string|max:50|unique:warehouses,code,' . $id, 'name' => 'required|string|max:191', 'location' => 'nullable|string|max:191', 'type' => 'required|in:raw_material,wip,finished_goods', 'notes' => 'nullable|string', 'is_active' => 'required|boolean']);
        DB::table('warehouses')->where('id', $id)->update($data + ['updated_at' => now()]);
        return back()->with('success', 'Warehouse updated.');
    }

    public function locationsStore(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id', 'location_code' => 'required|string|max:100',
            'location_name' => 'nullable|string|max:191',
        ]);
        DB::table('locations')->insert([
            ...$data, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Storage location created');
    }

    public function locationUpdate(Request $request, int $id)
    {
        $data = $request->validate(['warehouse_id' => 'required|exists:warehouses,id', 'location_code' => 'required|string|max:100', 'location_name' => 'nullable|string|max:191', 'is_active' => 'required|boolean']);
        $exists = DB::table('locations')->where('warehouse_id', $data['warehouse_id'])->where('location_code', $data['location_code'])->where('id', '!=', $id)->exists();
        if ($exists) return back()->withInput()->with('error', 'Location code already exists in the selected warehouse.');
        DB::table('locations')->where('id', $id)->update($data + ['updated_at' => now()]);
        return back()->with('success', 'Storage location updated.');
    }

    // ============ INVENTORY ITEMS ============
    public function index(Request $request)
    {
        $query = DB::table('inventory_balances')
            ->join('materials', 'inventory_balances.material_id', '=', 'materials.id')
            ->select('inventory_balances.id', 'materials.internal_code as material_code', 'materials.material_name',
                'materials.material_type', 'materials.unit', 'inventory_balances.balance_qty as current_qty',
                'inventory_balances.balance_qty as available_qty', 'inventory_balances.unit_cost',
                'inventory_balances.location as location_bin', 'inventory_balances.lot_roll_no as batch_no',
                DB::raw('0 as reserved_qty'), DB::raw('0 as min_stock_level'), DB::raw('0 as reorder_point'),
                DB::raw('NULL as warehouse_id'), DB::raw('NULL as warehouse_name'), DB::raw('NULL as warehouse_code'));

        if ($request->filled('material_code')) {
            $query->where('materials.internal_code', 'like', '%' . $request->material_code . '%');
        }
        if ($request->filled('material_type')) {
            $query->where('materials.material_type', $request->material_type);
        }
        if ($request->filled('low_stock')) {
            $query->whereRaw('inventory_balances.balance_qty <= 0');
        }

        $items = $query->orderBy('materials.internal_code')
            ->paginate(20);

        $warehouses = DB::table('warehouses')->orderBy('name')->get();

        return view('admin.inventory.index', compact('items', 'warehouses'));
    }

    public function adjust(Request $request, $id, InventoryLedgerService $ledger)
    {
        $request->validate([
            'new_qty' => 'required|numeric|min:0',
            'reason' => 'required',
        ]);

        $item = DB::table('inventory_balances')->find($id);
        if (!$item) abort(404);

        $oldQty = $item->balance_qty;
        $ledger->adjust([
            'balance_id' => $id, 'new_qty' => $request->new_qty, 'reason' => $request->reason,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', "Inventory adjusted: {$item->material_code} ($oldQty → {$request->new_qty})");
    }

    // ============ TRANSACTIONS ============
    public function transactions(Request $request)
    {
        $transactions = DB::table('inventory_transactions')
            ->leftJoin('warehouses as fw', 'inventory_transactions.from_warehouse_id', '=', 'fw.id')
            ->leftJoin('warehouses as tw', 'inventory_transactions.to_warehouse_id', '=', 'tw.id')
            ->select('inventory_transactions.*', 'fw.name as from_warehouse', 'tw.name as to_warehouse')
            ->when($request->filled('material_code'), fn($q) => $q->where('inventory_transactions.material_code', 'like', '%'.$request->material_code.'%'))
            ->when($request->filled('type'), fn($q) => $q->where('inventory_transactions.transaction_type', $request->type))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('inventory_transactions.created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('inventory_transactions.created_at', '<=', $request->date_to))
            ->orderBy('inventory_transactions.created_at', 'desc')
            ->paginate(20);

        return view('admin.inventory.transactions', compact('transactions'));
    }

    // ============ STOCK REPORT ============
    public function stockReport(Request $request)
    {
        $summary = DB::table('inventory_balances')
            ->join('materials', 'inventory_balances.material_id', '=', 'materials.id')
            ->select(
                'materials.material_type',
                DB::raw('COUNT(*) as item_count'),
                DB::raw('SUM(inventory_balances.balance_qty) as total_qty'),
                DB::raw('SUM(inventory_balances.balance_qty) as total_available'),
                DB::raw('SUM(inventory_balances.balance_qty * inventory_balances.unit_cost) as total_value')
            )
            ->groupBy('materials.material_type')
            ->get();

        $lowStock = DB::table('inventory_balances')
            ->join('materials', 'inventory_balances.material_id', '=', 'materials.id')
            ->select('materials.internal_code as material_code', 'materials.material_name', 'materials.material_type',
                'inventory_balances.balance_qty as available_qty', DB::raw('0 as reorder_point'),
                DB::raw('0 as min_stock_level'), DB::raw('NULL as warehouse_name'))
            ->where('inventory_balances.balance_qty', '<=', 0)
            ->get();

        return view('admin.inventory.report', compact('summary', 'lowStock'));
    }
}
