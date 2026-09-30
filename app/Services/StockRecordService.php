<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    private function supplyPools(object $record, Collection $balances, int $materialId): array
    {
        $keyFor = fn ($color, $size) => json_encode([
            mb_strtolower(trim((string) ($color ?? ''))), mb_strtolower(trim((string) ($size ?? ''))),
        ]);
        $current = $balances->groupBy(fn ($balance) => $keyFor($balance->material_color, $balance->material_size));
        $reservedBySpec = $current->map(fn ($rows) => (float) $rows->sum('reserved_qty'));
        $transactions = DB::table('inventory_transactions')->where('material_id', $materialId)
            ->where('id', '>', (int) $record->opening_transaction_id)->orderBy('transaction_date')->orderBy('id')->get();
        $receiptNumbers = collect();
        if (Schema::hasTable('po_receipts') && Schema::hasTable('purchase_orders')) {
            $receiptNumbers = DB::table('po_receipts as receipt')->join('purchase_orders as po', 'po.id', '=', 'receipt.po_id')
                ->pluck('po.po_number', 'receipt.id');
        }
        $movementBySpec = $transactions->groupBy(fn ($tx) => $keyFor($tx->material_color, $tx->material_size))
            ->map(fn ($rows) => (float) $rows->sum('quantity'));
        $pools = [];
        foreach ($current as $specKey => $rows) {
            $currentQty = (float) $rows->sum('balance_qty');
            $openingQty = max(0, $currentQty - (float) ($movementBySpec[$specKey] ?? 0));
            $layers = [];
            if ($openingQty > 0) {
                $layers[] = ['source' => 'Opening / other stock', 'date' => '0000-00-00', 'sequence' => 0, 'qty' => $openingQty];
            }
            foreach ($transactions->filter(fn ($tx) => $keyFor($tx->material_color, $tx->material_size) === $specKey) as $tx) {
                $qty = (float) $tx->quantity;
                if ($qty > 0) {
                    $source = $tx->reference_type === 'PO_RECEIPT'
                        ? ($receiptNumbers[$tx->reference_id] ?? 'PO receipt #'.$tx->reference_id)
                        : ($tx->reference_type === 'MATERIAL_ISSUE_REVERSAL' ? 'Issue reversal' : 'Other receipt');
                    $layers[] = ['source' => $source, 'date' => (string) $tx->transaction_date, 'sequence' => (int) $tx->id, 'qty' => $qty];
                } elseif ($qty < 0) {
                    $toRemove = -$qty;
                    foreach ($layers as &$layer) {
                        $take = min($toRemove, $layer['qty']);
                        $layer['qty'] -= $take;
                        $toRemove -= $take;
                        if ($toRemove <= 0.00001) break;
                    }
                    unset($layer);
                    $layers = array_values(array_filter($layers, fn ($layer) => $layer['qty'] > 0.00001));
                }
            }
            $layerQty = array_sum(array_column($layers, 'qty'));
            if ($layerQty > $currentQty + 0.00001) {
                $toRemove = $layerQty - $currentQty;
                foreach ($layers as &$layer) {
                    $take = min($toRemove, $layer['qty']);
                    $layer['qty'] -= $take;
                    $toRemove -= $take;
                    if ($toRemove <= 0.00001) break;
                }
                unset($layer);
                $layers = array_values(array_filter($layers, fn ($layer) => $layer['qty'] > 0.00001));
            } elseif ($layerQty + 0.00001 < $currentQty) {
                array_unshift($layers, ['source' => 'Opening / other stock', 'date' => '0000-00-00', 'sequence' => 0, 'qty' => $currentQty - $layerQty]);
            }
            $layers = collect($layers)->groupBy('source')->map(function ($sourceLayers, $source) {
                return [
                    'source' => $source, 'date' => $sourceLayers->min('date'),
                    'sequence' => $sourceLayers->min('sequence'), 'qty' => (float) $sourceLayers->sum('qty'),
                ];
            })->sortBy(fn ($layer) => [$layer['date'], $layer['sequence']])->values()->all();
            $reservedLeft = min($currentQty, (float) ($reservedBySpec[$specKey] ?? 0));
            foreach ($layers as $layer) {
                $reservedQty = min($reservedLeft, $layer['qty']);
                $reservedLeft -= $reservedQty;
                $pools[$specKey][] = [
                    'material_color' => $rows->first()->material_color, 'material_size' => $rows->first()->material_size,
                    'source' => $layer['source'], 'date' => $layer['date'], 'sequence' => $layer['sequence'], 'reserved' => $reservedQty,
                    'free' => $layer['qty'] - $reservedQty,
                ];
            }
        }
        foreach ($pools as &$specPools) {
            usort($specPools, fn ($a, $b) => [$a['date'], $a['sequence']] <=> [$b['date'], $b['sequence']]);
        }
        unset($specPools);
        return $pools;
    }

    /** Simulates allocation only; never changes balances or actual reservations. */
    public function plan(object $record, OrderMaterialRequirementService $requirements, bool $lock = false): Collection
    {
        $orders = $this->activeOrders($record->material_id, $lock);
        $priorities = DB::table('stock_record_priorities')->where('stock_record_id', $record->id)->when($lock, fn ($q) => $q->lockForUpdate())->pluck('sort_order', 'cutsheet_id');
        $orders = $orders->sortBy(fn ($order) => $priorities[$order->id] ?? PHP_INT_MAX, SORT_REGULAR)->values();
        $balances = DB::table('inventory_balances')->where('material_id', $record->material_id)->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $supplyPools = $this->supplyPools($record, $balances, (int) $record->material_id);
        $issued = DB::table('requisition_items as item')->join('material_requisitions as req', 'req.id', '=', 'item.requisition_id')
            ->where('item.material_id', $record->material_id)->whereIn('req.cutsheet_id', $orders->pluck('id'))
            ->select('req.cutsheet_id', 'item.material_color', 'item.material_size', 'item.issued_qty')->when($lock, fn ($q) => $q->lockForUpdate())->get()->groupBy('cutsheet_id');
        $reservations = DB::table('inventory_reservations as res')
            ->join('requisition_items as item', 'item.id', '=', 'res.requisition_item_id')
            ->join('material_requisitions as req', 'req.id', '=', 'item.requisition_id')
            ->where('res.status', 'active')->where('item.material_id', $record->material_id)
            ->select('res.*', 'req.cutsheet_id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $reservedLeft = $reservations->mapWithKeys(fn ($r) => [$r->id => max(0, (float) $r->reserved_qty - (float) $r->consumed_qty)])->all();
        $projected = (float) $balances->sum('balance_qty');
        $plan = collect();
        foreach ($orders as $index => $order) {
            $needs = $requirements->sync($order->id)->where('material_id', $record->material_id);
            // Consume issued quantities once even when multiple BOM lines use the same material.
            $orderIssued = ($issued[$order->id] ?? collect())->map(fn ($r) => clone $r);
            $required = $remaining = $covered = $reserved = 0.0;
            $poAllocations = [];
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
                    $take = min($qty, $reservedLeft[$reservation->id]);
                    $specKey = json_encode([mb_strtolower(trim((string) ($balance->material_color ?? ''))), mb_strtolower(trim((string) ($balance->material_size ?? '')))]);
                    if (isset($supplyPools[$specKey])) foreach ($supplyPools[$specKey] as &$pool) {
                        $poolTake = min($take, $pool['reserved']);
                        if ($poolTake <= 0) continue;
                        $pool['reserved'] -= $poolTake; $take -= $poolTake;
                        $qty -= $poolTake; $reservedLeft[$reservation->id] -= $poolTake;
                        $covered += $poolTake; $reserved += $poolTake;
                        $poAllocations[$pool['source']] = ($poAllocations[$pool['source']] ?? 0) + $poolTake;
                        if ($take <= 0.00001) break;
                    }
                    unset($pool);
                }
                foreach ($supplyPools as &$specPools) {
                    if (!$specPools || !$this->matches((object) $specPools[0], $need)) continue;
                    foreach ($specPools as &$pool) {
                        $take = min($qty, $pool['free']);
                        if ($take <= 0) continue;
                        $qty -= $take; $pool['free'] -= $take; $covered += $take;
                        $poAllocations[$pool['source']] = ($poAllocations[$pool['source']] ?? 0) + $take;
                        if ($qty <= 0.00001) break;
                    }
                    unset($pool);
                    if ($qty <= 0.00001) break;
                }
                unset($specPools);
            }
            $projected -= $remaining;
            $bom = DB::table('bom_headers')->find($order->bom_header_id);
            $plan->push((object) [
                'order' => $order, 'bom' => $bom, 'priority' => $priorities[$order->id] ?? ((int) $priorities->max() + ($index + 1) * 10),
                'required' => $required, 'issued' => (float) ($issued[$order->id] ?? collect())->sum('issued_qty'),
                'remaining' => $remaining, 'reserved' => $reserved, 'covered' => $covered,
                'po_allocations' => collect($poAllocations)->map(fn ($qty, $source) => (object) ['source' => $source, 'quantity' => $qty])->values(),
                'shortage' => max(0, round($remaining - $covered, 4)), 'projected' => round($projected, 4),
            ]);
        }
        return $plan;
    }
}
