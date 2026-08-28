<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryLedgerService
{
    /** Every stock movement is recorded before the derived balance is updated. */
    public function receive(array $data): void
    {
        DB::transaction(function () use ($data) {
            // Lock the material row as a stable mutex even when this is the first
            // lot/location balance and therefore no balance row exists yet.
            DB::table('materials')->where('id', $data['material_id'])->lockForUpdate()->firstOrFail();
            $balance = DB::table('inventory_balances')
                ->where('material_id', $data['material_id'])
                ->where('warehouse_id', $data['warehouse_id'] ?? null)
                ->where('location_id', $data['location_id'] ?? null)
                ->where('material_color', $data['color'] ?? null)
                ->where('material_size', $data['size'] ?? null)
                ->where('lot_roll_no', $data['lot_roll_no'] ?? null)
                ->where('location', $data['location'] ?? null)
                ->lockForUpdate()->first();

            if ($balance) {
                $newQty = $balance->balance_qty + $data['quantity'];
                $newCost = $newQty > 0
                    ? (($balance->balance_qty * $balance->unit_cost) + ($data['quantity'] * ($data['unit_cost'] ?? 0))) / $newQty
                    : 0;
                DB::table('inventory_balances')->where('id', $balance->id)->update([
                    'balance_qty' => $newQty, 'unit_cost' => $newCost, 'updated_at' => now(),
                ]);
            } else {
                DB::table('inventory_balances')->insert([
                    'material_id' => $data['material_id'], 'warehouse_id' => $data['warehouse_id'] ?? null,
                    'location_id' => $data['location_id'] ?? null, 'material_color' => $data['color'] ?? null,
                    'material_size' => $data['size'] ?? null, 'lot_roll_no' => $data['lot_roll_no'] ?? null,
                    'location' => $data['location'] ?? null, 'balance_qty' => $data['quantity'],
                    'unit_cost' => $data['unit_cost'] ?? 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('inventory_transactions')->insert([
                'transaction_type' => 'IN', 'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'] ?? null, 'reference_doc' => $data['reference_doc'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'material_id' => $data['material_id'], 'material_code' => $data['material_code'],
                'material_color' => $data['color'] ?? null, 'material_size' => $data['size'] ?? null,
                'lot_roll_no' => $data['lot_roll_no'] ?? null, 'location' => $data['location'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'quantity' => $data['quantity'], 'unit' => $data['unit'],
                'to_warehouse_id' => $data['warehouse_id'] ?? null, 'unit_cost' => $data['unit_cost'] ?? 0,
                'total_cost' => $data['quantity'] * ($data['unit_cost'] ?? 0), 'notes' => $data['notes'] ?? null,
                'created_by' => $data['user_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function issue(array $data): void
    {
        DB::transaction(function () use ($data) {
            $balance = DB::table('inventory_balances')->where('id', $data['balance_id'])->lockForUpdate()->first();
            if (!$balance) {
                throw new RuntimeException('Insufficient available stock for this lot/roll.');
            }
            $reservation = null;
            if (!empty($data['requisition_item_id'])) {
                $reservation = DB::table('inventory_reservations')
                    ->where('requisition_item_id', $data['requisition_item_id'])
                    ->where('inventory_balance_id', $balance->id)->where('status', 'active')->lockForUpdate()->first();
                if ($reservation && ($reservation->reserved_qty - $reservation->consumed_qty) < $data['quantity']) {
                    throw new RuntimeException('Issue quantity exceeds the stock reserved for this requisition item.');
                }
                if ($reservation) {
                    DB::table('inventory_reservations')->where('id', $reservation->id)->update([
                        'consumed_qty' => $reservation->consumed_qty + $data['quantity'],
                        'status' => ($reservation->reserved_qty - $reservation->consumed_qty - $data['quantity']) <= 0 ? 'consumed' : 'active',
                        'updated_at' => now(),
                    ]);
                    DB::table('inventory_balances')->where('id', $balance->id)->decrement('reserved_qty', $data['quantity'], ['updated_at' => now()]);
                }
            }
            if ($balance->balance_qty < $data['quantity'] || (!$reservation && ($balance->balance_qty - $balance->reserved_qty) < $data['quantity'])) {
                throw new RuntimeException('Insufficient available stock for this lot/roll.');
            }
            DB::table('inventory_balances')->where('id', $balance->id)->update([
                'balance_qty' => $balance->balance_qty - $data['quantity'], 'updated_at' => now(),
            ]);
            DB::table('inventory_transactions')->insert([
                'transaction_type' => 'OUT', 'reference_type' => 'MATERIAL_ISSUE', 'reference_id' => $data['issue_id'],
                'reference_doc' => $data['issue_code'], 'transaction_date' => $data['issue_date'],
                'material_id' => $balance->material_id, 'material_code' => $data['material_code'],
                'material_color' => $balance->material_color, 'material_size' => $balance->material_size,
                'lot_roll_no' => $balance->lot_roll_no, 'location' => $balance->location,
                'location_id' => $balance->location_id,
                'quantity' => -$data['quantity'], 'unit' => $data['unit'],
                'from_warehouse_id' => $data['warehouse_id'] ?? null, 'unit_cost' => $balance->unit_cost,
                'total_cost' => -$data['quantity'] * $balance->unit_cost, 'notes' => $data['notes'] ?? null,
                'created_by' => $data['user_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Reserve the currently available lots for a requisition item. Returns reserved quantity. */
    public function reserve(array $data): float
    {
        return DB::transaction(function () use ($data) {
            $remaining = (float) $data['quantity'];
            $reserved = 0.0;
            $balances = DB::table('inventory_balances')->where('material_id', $data['material_id'])
                ->when($data['color'] ?? null, fn ($q, $color) => $q->where('material_color', $color))
                ->when($data['size'] ?? null, fn ($q, $size) => $q->where('material_size', $size))
                ->where('balance_qty', '>', 0)->orderBy('id')->lockForUpdate()->get();
            foreach ($balances as $balance) {
                if ($remaining <= 0) break;
                $available = max(0, (float) $balance->balance_qty - (float) $balance->reserved_qty);
                $qty = min($remaining, $available);
                if ($qty <= 0) continue;
                DB::table('inventory_balances')->where('id', $balance->id)->increment('reserved_qty', $qty, ['updated_at' => now()]);
                $existing = DB::table('inventory_reservations')->where('requisition_item_id', $data['requisition_item_id'])
                    ->where('inventory_balance_id', $balance->id)->lockForUpdate()->first();
                if ($existing) {
                    DB::table('inventory_reservations')->where('id', $existing->id)->update([
                        'reserved_qty' => $existing->reserved_qty + $qty, 'status' => 'active', 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('inventory_reservations')->insert([
                        'requisition_item_id' => $data['requisition_item_id'], 'inventory_balance_id' => $balance->id,
                        'reserved_qty' => $qty, 'consumed_qty' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $material = DB::table('materials')->where('id', $balance->material_id)->firstOrFail();
                DB::table('inventory_transactions')->insert([
                    'transaction_type' => 'RESERVE', 'reference_type' => 'REQUISITION_ITEM', 'reference_id' => $data['requisition_item_id'],
                    'transaction_date' => now()->toDateString(), 'material_id' => $balance->material_id, 'material_code' => $material->internal_code,
                    'material_color' => $balance->material_color, 'material_size' => $balance->material_size, 'lot_roll_no' => $balance->lot_roll_no,
                    'location' => $balance->location, 'location_id' => $balance->location_id, 'quantity' => 0, 'unit' => $material->unit,
                    'unit_cost' => $balance->unit_cost, 'total_cost' => 0, 'notes' => 'Reserved quantity: ' . $qty,
                    'created_by' => $data['user_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $remaining -= $qty;
                $reserved += $qty;
            }
            return $reserved;
        });
    }

    /** Release every unconsumed reservation belonging to a cancelled requisition. */
    public function releaseForRequisition(int $requisitionId, ?int $userId = null): void
    {
        DB::transaction(function () use ($requisitionId, $userId) {
            $reservations = DB::table('inventory_reservations')
                ->join('requisition_items', 'inventory_reservations.requisition_item_id', '=', 'requisition_items.id')
                ->where('requisition_items.requisition_id', $requisitionId)
                ->where('inventory_reservations.status', 'active')
                ->select('inventory_reservations.*')->lockForUpdate()->get();
            foreach ($reservations as $reservation) {
                $balance = DB::table('inventory_balances')->where('id', $reservation->inventory_balance_id)->lockForUpdate()->first();
                $qty = max(0, (float) $reservation->reserved_qty - (float) $reservation->consumed_qty);
                if (!$balance || $qty <= 0) continue;
                $material = DB::table('materials')->where('id', $balance->material_id)->firstOrFail();
                DB::table('inventory_balances')->where('id', $balance->id)->decrement('reserved_qty', $qty, ['updated_at' => now()]);
                DB::table('inventory_reservations')->where('id', $reservation->id)->update(['status' => 'released', 'updated_at' => now()]);
                DB::table('inventory_transactions')->insert([
                    'transaction_type' => 'UNRESERVE', 'reference_type' => 'REQUISITION_ITEM', 'reference_id' => $reservation->requisition_item_id,
                    'transaction_date' => now()->toDateString(), 'material_id' => $balance->material_id, 'material_code' => $material->internal_code,
                    'material_color' => $balance->material_color, 'material_size' => $balance->material_size, 'lot_roll_no' => $balance->lot_roll_no,
                    'location' => $balance->location, 'location_id' => $balance->location_id, 'quantity' => 0, 'unit' => $material->unit,
                    'unit_cost' => $balance->unit_cost, 'total_cost' => 0, 'notes' => 'Released reserved quantity: ' . $qty,
                    'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function adjust(array $data): void
    {
        DB::transaction(function () use ($data) {
            $balance = DB::table('inventory_balances')->where('id', $data['balance_id'])->lockForUpdate()->first();
            if (!$balance) throw new RuntimeException('Inventory balance was not found.');
            $difference = $data['new_qty'] - $balance->balance_qty;
            DB::table('inventory_balances')->where('id', $balance->id)->update([
                'balance_qty' => $data['new_qty'], 'updated_at' => now(),
            ]);
            $material = DB::table('materials')->where('id', $balance->material_id)->firstOrFail();
            DB::table('inventory_transactions')->insert([
                'transaction_type' => 'ADJUSTMENT', 'reference_type' => 'ADJUSTMENT', 'reference_id' => $balance->id,
                'transaction_date' => now()->toDateString(), 'material_id' => $balance->material_id,
                'material_code' => $material->internal_code, 'material_color' => $balance->material_color,
                'material_size' => $balance->material_size, 'lot_roll_no' => $balance->lot_roll_no,
                'location' => $balance->location, 'location_id' => $balance->location_id,
                'quantity' => $difference, 'unit' => $material->unit,
                'unit_cost' => $balance->unit_cost, 'total_cost' => $difference * $balance->unit_cost,
                'notes' => $data['reason'], 'created_by' => $data['user_id'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
