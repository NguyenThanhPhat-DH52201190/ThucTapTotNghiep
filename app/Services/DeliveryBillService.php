<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryBillService
{
    private function fail(string $message, string $field = 'items'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function key(object $row): string
    {
        return json_encode([(int) $row->material_id, mb_strtolower(trim((string) $row->material_color)), mb_strtolower(trim((string) $row->material_size))]);
    }

    public function matches(object $balance, object $need): bool
    {
        if ((int) $balance->material_id !== (int) $need->material_id) return false;
        foreach (['material_color', 'material_size'] as $field) {
            if (trim((string) $need->$field) !== '' && strcasecmp(trim((string) $balance->$field), trim((string) $need->$field)) !== 0) return false;
        }
        return true;
    }

    public function remaining(int $id, $needs, bool $lock = false): array
    {
        $issued = DB::table('requisition_items as item')->join('material_requisitions as req', 'req.id', '=', 'item.requisition_id')
            ->where('req.cutsheet_id', $id)->select('item.*')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $remaining = [];
        foreach ($needs->groupBy(fn ($n) => $this->key($n))->sortByDesc(fn ($g) => (int) !empty($g->first()->material_color) + (int) !empty($g->first()->material_size)) as $key => $group) {
            $qty = (float) $group->sum('required_qty');
            foreach ($issued as $entry) {
                if (!$this->matches($entry, $group->first())) continue;
                $take = min($qty, (float) $entry->issued_qty);
                $qty -= $take; $entry->issued_qty -= $take;
            }
            $remaining[$key] = max(0, round($qty, 4));
        }
        return $remaining;
    }

    public function confirm(int $id, array $data, int $userId): object
    {
        $path = null;
        try {
            return DB::transaction(function () use ($id, $data, $userId, &$path) {
                $order = DB::table('ocs')->where('id', $id)->lockForUpdate()->first();
                abort_unless($order, 404);
                $existing = DB::table('delivery_bills')->where('submission_key', $data['submission_key'])->first();
                if ($existing) {
                    abort_unless((int) $existing->cutsheet_id === $id, 409);
                    return $existing;
                }
                $issuedOn = now(config('delivery-bills.timezone'))->toDateString();
                if (!in_array($order->status, ['pending', 'confirmed', 'in_production', 'released'], true)) $this->fail('This CU is not active.');
                if (!$order->bom_header_id) $this->fail('Assign a BOM before issuing materials.');
                if (DB::table('delivery_bills')->where('number', $data['number'])->exists() || DB::table('material_issues')->where('issue_code', $data['number'])->exists()) $this->fail('This delivery bill number is already in use.', 'number');

                $requirements = app(OrderMaterialRequirementService::class);
                $needs = $requirements->sync($id);
                $selected = $needs->whereIn('bom_item_id', array_column($data['items'], 'bom_item_id'));
                $materialIds = $selected->pluck('material_id')->filter()->unique()->sort()->values();
                DB::table('materials')->whereIn('id', $materialIds)->orderBy('id')->lockForUpdate()->get();
                // Priority editors take the same stock-record locks. Lock balances across all
                // locations because priority allocation uses the entire matching stock pool.
                $records = DB::table('stock_records')->whereIn('material_id', $materialIds)->orderBy('material_id')->lockForUpdate()->get()->keyBy('material_id');
                $balances = DB::table('inventory_balances')->whereIn('material_id', $materialIds)->orderBy('id')->lockForUpdate()->get();
                foreach ($materialIds as $materialId) {
                    if (isset($records[$materialId])) continue;
                    $recordId = DB::table('stock_records')->insertGetId([
                        'material_id' => $materialId, 'opening_qty' => $balances->where('material_id', $materialId)->sum('balance_qty'),
                        'opening_transaction_id' => DB::table('inventory_transactions')->where('material_id', $materialId)->max('id') ?? 0,
                        'opened_at' => now(), 'sort_order' => 1000, 'revision' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $records[$materialId] = DB::table('stock_records')->find($recordId);
                }
                $stock = app(StockRecordService::class);
                $before = $records->map(fn ($record) => $stock->plan($record, $requirements, true));
                $remaining = $this->remaining($id, $needs, true);
                $req = DB::table('material_requisitions')->where('cutsheet_id', $id)->whereIn('status', ['pending', 'partial', 'draft'])->orderBy('id')->lockForUpdate()->first();
                if (!$req) {
                    $reqId = DB::table('material_requisitions')->insertGetId(['cutsheet_id' => $id, 'requisition_code' => 'REQ-DLV-'.Str::upper(Str::random(16)), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                    $req = DB::table('material_requisitions')->find($reqId);
                }
                $issueId = DB::table('material_issues')->insertGetId(['issue_code' => $data['number'], 'requisition_id' => $req->id, 'issue_date' => $issuedOn, 'receiver_name' => $data['customer'], 'status' => 'issued', 'created_at' => now(), 'updated_at' => now()]);
                $lines = [];
                foreach ($data['items'] as $entry) {
                    $need = $needs->firstWhere('bom_item_id', $entry['bom_item_id']);
                    $balance = DB::table('inventory_balances')->where('id', $entry['balance_id'])->lockForUpdate()->first();
                    if (!$need || !$need->material_id || !$balance || !$this->matches($balance, $need)) $this->fail('Select a matching NORM material, colour, size and inventory lot.');
                    $qty = (float) $entry['quantity'];
                    $key = $this->key($need);
                    $reqItems = DB::table('requisition_items')->where('requisition_id', $req->id)->where('material_id', $need->material_id)->orderBy('id')->lockForUpdate()->get();
                    $item = $reqItems->first(fn ($r) => $this->key($r) === $key && (float) $r->requested_qty > (float) $r->issued_qty);
                    if (!$item) {
                        $itemId = DB::table('requisition_items')->insertGetId(['requisition_id' => $req->id, 'material_id' => $need->material_id, 'material_color' => $need->material_color, 'material_size' => $need->material_size, 'requested_qty' => max($qty, $remaining[$key]), 'issued_qty' => 0, 'created_at' => now(), 'updated_at' => now()]);
                        $item = DB::table('requisition_items')->find($itemId);
                    }
                    // This confirmed delivery may include additional consumption beyond NORM.
                    // Expand the request in the same transaction; stock safeguards still apply.
                    $requested = max((float) $item->requested_qty, round((float) $item->issued_qty + $qty, 4));
                    if ($requested > (float) $item->requested_qty) {
                        DB::table('requisition_items')->where('id', $item->id)->update(['requested_qty' => $requested, 'updated_at' => now()]);
                    }
                    $reservation = DB::table('inventory_reservations')->where('requisition_item_id', $item->id)->where('inventory_balance_id', $balance->id)->where('status', 'active')->lockForUpdate()->first();
                    $own = $reservation ? max(0, (float) $reservation->reserved_qty - (float) $reservation->consumed_qty) : 0;
                    if ($qty > (float) $balance->balance_qty || $qty > max(0, (float) $balance->balance_qty - (float) $balance->reserved_qty) + $own + .00001) $this->fail("{$need->material_code}: insufficient usable stock; other reservations cannot be used.");
                    $notes = 'CU '.$order->CS.'; '.$data['reason'].(!empty($data['priority_reason']) ? '; Priority override: '.$data['priority_reason'] : '');
                    foreach ([round(min($qty, $own), 4), round(max(0, $qty - $own), 4)] as $part => $amount) {
                        if ($amount <= 0) continue;
                        app(InventoryLedgerService::class)->issue([
                            'balance_id' => $balance->id, 'requisition_item_id' => $part === 0 ? $item->id : null,
                            'issue_id' => $issueId, 'issue_code' => $data['number'], 'issue_date' => $issuedOn,
                            'material_code' => $need->material_code, 'unit' => $need->unit, 'quantity' => $amount,
                            'warehouse_id' => $balance->warehouse_id, 'user_id' => $userId, 'notes' => $notes,
                        ]);
                    }
                    DB::table('issue_items')->insert(['issue_id' => $issueId, 'requisition_item_id' => $item->id, 'material_id' => $balance->material_id,
                        'material_color' => $balance->material_color, 'material_size' => $balance->material_size, 'lot_roll_no' => $balance->lot_roll_no,
                        'location' => $balance->location, 'location_id' => $balance->location_id, 'issued_qty' => $qty, 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('requisition_items')->where('id', $item->id)->increment('issued_qty', $qty, ['updated_at' => now()]);
                    $remaining[$key] = max(0, round($remaining[$key] - $qty, 4));
                    $lines[] = ['code' => $need->material_code, 'description' => $need->material_name, 'colour' => $balance->material_color, 'size' => $balance->material_size,
                        'unit' => $need->unit, 'quantity' => $qty, 'balance_id' => $balance->id, 'bom_item_id' => $need->bom_item_id,
                        'warehouse_id' => $balance->warehouse_id, 'lot_no' => $balance->lot_no, 'roll_no' => $balance->roll_no];
                }
                $warnings = []; $snapshots = [];
                foreach ($records as $materialId => $record) {
                    $after = $stock->plan($record, $requirements, true)->keyBy(fn ($r) => $r->order->id);
                    foreach ($before[$materialId] as $row) {
                        $snapshots[] = ['material_id' => $materialId, 'revision' => $record->revision, 'cu' => $row->order->CS, 'priority' => $row->priority, 'covered_before' => $row->covered, 'covered_after' => $after[$row->order->id]->covered ?? 0];
                    }
                    foreach ($before[$materialId] as $row) {
                        if ((int) $row->order->id === $id) break;
                        $lost = $row->covered - ($after[$row->order->id]->covered ?? 0);
                        $materialCode = $needs->firstWhere('material_id', $materialId)->material_code;
                        if ($lost > .0001) $warnings[] = "{$materialCode}: higher-priority CU {$row->order->CS} loses ".round($lost, 4).' of its planned allocation.';
                    }
                }
                if ($warnings && trim((string) ($data['priority_reason'] ?? '')) === '') $this->fail(implode(' ', $warnings).' Enter a priority override reason to confirm.', 'priority_reason');
                $partial = DB::table('requisition_items')->where('requisition_id', $req->id)->whereColumn('issued_qty', '<', 'requested_qty')->exists();
                DB::table('material_requisitions')->where('id', $req->id)->update(['status' => $partial ? 'partial' : 'completed', 'updated_at' => now()]);
                $header = array_intersect_key($data, array_flip(['number', 'customer', 'address', 'reason', 'shipper', 'shipper_address']));
                $header['cu'] = $order->CS;
                $path = 'delivery-bills/'.Str::uuid().'.xlsx';
                app(DeliveryBillExcelService::class)->write($header, $lines, $issuedOn, $path);
                $billId = DB::table('delivery_bills')->insertGetId(['cutsheet_id' => $id, 'issue_id' => $issueId, 'submission_key' => $data['submission_key'], 'number' => $data['number'], 'issued_on' => $issuedOn,
                    'header' => json_encode($header, JSON_UNESCAPED_UNICODE), 'lines' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'priority_snapshot' => json_encode(['orders' => $snapshots, 'warnings' => $warnings], JSON_UNESCAPED_UNICODE),
                    'priority_reason' => $data['priority_reason'] ?? null, 'file_path' => $path, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
                app(AuditTrailService::class)->record('delivery_bill_issued', 'delivery_bill', $billId, $userId, [], ['issue_id' => $issueId, 'lines' => $lines, 'priority_warnings' => $warnings], $data['priority_reason'] ?? $data['reason']);
                return DB::table('delivery_bills')->find($billId);
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
