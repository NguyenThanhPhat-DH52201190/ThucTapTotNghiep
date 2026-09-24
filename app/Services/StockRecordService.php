<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockRecordService
{
    /** Opening snapshots are immutable; importing again only adds new inventory codes. */
    public function importInventory(): int
    {
        $added = 0;
        foreach (DB::table('inventory_balances')->distinct()->orderBy('material_id')->pluck('material_id') as $id) {
            $added += DB::transaction(function () use ($id) {
                DB::table('materials')->where('id', $id)->lockForUpdate()->firstOrFail();
                if (DB::table('stock_records')->where('material_id', $id)->exists()) return 0;
                $balances = DB::table('inventory_balances')->where('material_id', $id)->lockForUpdate()->get();
                // Read the ledger after obtaining the balance locks, including any just-committed issue.
                $last = DB::table('inventory_transactions')->where('material_id', $id)->orderByDesc('id')->lockForUpdate()->first();
                DB::table('stock_records')->insert([
                    'material_id' => $id, 'opening_qty' => $balances->sum('balance_qty'),
                    'opening_transaction_id' => $last?->id ?? 0, 'opened_at' => now(),
                    'sort_order' => 1000, 'revision' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                return 1;
            }, 3);
        }
        return $added;
    }

    public function activeOrders(int $materialId, bool $lock = false): Collection
    {
        return DB::table('ocs')->whereIn('status', ['pending', 'confirmed', 'in_production', 'released'])
            ->where(function ($query) use ($materialId) {
                $query->whereExists(fn ($q) => $q->selectRaw('1')->from('bom_items')
                    ->whereColumn('bom_items.bom_header_id', 'ocs.bom_header_id')->where('bom_items.material_id', $materialId));
                if (\Illuminate\Support\Facades\Schema::hasTable('norm_material_replacements')) {
                    $query->orWhereExists(fn ($q) => $q->selectRaw('1')->from('norm_material_replacements as replacement')
                        ->join('bom_items as source', 'source.id', '=', 'replacement.bom_item_id')
                        ->whereColumn('replacement.cutsheet_id', 'ocs.id')
                        ->whereColumn('replacement.bom_header_id', 'ocs.bom_header_id')
                        ->whereColumn('source.bom_header_id', 'ocs.bom_header_id')->where('replacement.material_id', $materialId));
                }
            })
            ->orderByRaw('expected_ship_date IS NULL')->orderBy('expected_ship_date')->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())->get();
    }

    private function matches(object $stock, object $need): bool
    {
        foreach (['material_color', 'material_size'] as $field) {
            $value = trim((string) ($need->$field ?? ''));
            if ($value !== '' && strcasecmp(trim((string) ($stock->$field ?? '')), $value) !== 0) return false;
        }
        return true;
    }

    /** Simulates allocation only; never changes balances or actual reservations. */
    public function plan(object $record, OrderMaterialRequirementService $requirements, bool $lock = false): Collection
    {
        $orders = $this->activeOrders($record->material_id, $lock);
        $priorities = DB::table('stock_record_priorities')->where('stock_record_id', $record->id)->when($lock, fn ($q) => $q->lockForUpdate())->pluck('sort_order', 'cutsheet_id');
        $orders = $orders->sortBy(fn ($order) => $priorities[$order->id] ?? PHP_INT_MAX, SORT_REGULAR)->values();
        $balances = DB::table('inventory_balances')->where('material_id', $record->material_id)->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $free = $balances->mapWithKeys(fn ($b) => [$b->id => max(0, (float) $b->balance_qty - (float) $b->reserved_qty)])->all();
        $issued = DB::table('requisition_items as item')->join('material_requisitions as req', 'req.id', '=', 'item.requisition_id')
            ->where('item.material_id', $record->material_id)->whereIn('req.cutsheet_id', $orders->pluck('id'))
            ->select('req.cutsheet_id', 'item.material_color', 'item.material_size', 'item.issued_qty')->when($lock, fn ($q) => $q->lockForUpdate())->get()->groupBy('cutsheet_id');
        $reservations = DB::table('inventory_reservations as res')
            ->join('requisition_items as item', 'item.id', '=', 'res.requisition_item_id')
            ->join('material_requisitions as req', 'req.id', '=', 'item.requisition_id')
            ->where('res.status', 'active')->where('item.material_id', $record->material_id)
            ->select('res.*', 'req.cutsheet_id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $reservedLeft = $reservations->mapWithKeys(fn ($r) => [$r->id => max(0, (float) $r->reserved_qty - (float) $r->consumed_qty)])->all();
        $reservedCapacity = $balances->mapWithKeys(fn ($b) => [$b->id => max(0, min((float) $b->balance_qty, (float) $b->reserved_qty))])->all();
        $projected = (float) $balances->sum('balance_qty');
        $plan = collect();
        foreach ($orders as $index => $order) {
            $needs = $requirements->sync($order->id)->where('material_id', $record->material_id);
            // Consume issued quantities once even when multiple BOM lines use the same material.
            $orderIssued = ($issued[$order->id] ?? collect())->map(fn ($r) => clone $r);
            $required = $remaining = $covered = $reserved = 0.0;
            $groups = $needs->groupBy(fn ($r) => json_encode([$r->material_color, $r->material_size]))
                ->sortByDesc(fn ($g) => (int) !empty($g->first()->material_color) + (int) !empty($g->first()->material_size));
            foreach ($groups as $group) {
                $need = $group->first();
                $qty = (float) $group->sum('required_qty');
                $required += $qty;
                foreach ($orderIssued as $entry) {
                    if (!$this->matches($entry, $need)) continue;
                    $take = min($qty, (float) $entry->issued_qty);
                    $qty -= $take; $entry->issued_qty -= $take;
                }
                $remaining += $qty;
                foreach ($reservations->where('cutsheet_id', $order->id) as $reservation) {
                    $balance = $balances->firstWhere('id', $reservation->inventory_balance_id);
                    if (!$balance || !$this->matches($balance, $need)) continue;
                    $take = min($qty, $reservedLeft[$reservation->id], $reservedCapacity[$balance->id]);
                    $qty -= $take; $reservedLeft[$reservation->id] -= $take; $reservedCapacity[$balance->id] -= $take;
                    $covered += $take; $reserved += $take;
                }
                foreach ($balances as $balance) {
                    if (!$this->matches($balance, $need)) continue;
                    $take = min($qty, $free[$balance->id]);
                    $qty -= $take; $free[$balance->id] -= $take; $covered += $take;
                }
            }
            $projected -= $remaining;
            $bom = DB::table('bom_headers')->find($order->bom_header_id);
            $plan->push((object) [
                'order' => $order, 'bom' => $bom, 'priority' => $priorities[$order->id] ?? ((int) $priorities->max() + ($index + 1) * 10),
                'required' => $required, 'issued' => (float) ($issued[$order->id] ?? collect())->sum('issued_qty'),
                'remaining' => $remaining, 'reserved' => $reserved, 'covered' => $covered,
                'shortage' => max(0, round($remaining - $covered, 4)), 'projected' => round($projected, 4),
            ]);
        }
        return $plan;
    }
}
