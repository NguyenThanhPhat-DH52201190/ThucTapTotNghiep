<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class RequisitionService
{
    public function createForCutsheet(int $cutsheetId): int
    {
        return DB::transaction(function () use ($cutsheetId) {
            $order = DB::table('ocs')->where('id', $cutsheetId)->lockForUpdate()->first();
            if (!$order || !$order->bom_header_id) throw new RuntimeException('A confirmed order must have an assigned BOM.');
            $bom = DB::table('bom_headers')->where('id', $order->bom_header_id)->first();
            if (!$bom || $bom->status !== 'active') throw new RuntimeException('Confirmation requires an active BOM.');
            if (($bom->bom_kind ?? 'template') === 'order' && ($bom->mapping_status ?? null) !== 'ready') {
                throw new RuntimeException('Complete the Order BOM size mapping before confirmation.');
            }
            // Check only after obtaining the order lock; this makes confirmation idempotent
            // under concurrent HTTP requests.
            $existing = DB::table('material_requisitions')->where('cutsheet_id', $cutsheetId)->first();
            if ($existing) return $existing->id;
            $items = DB::table('bom_items')->where('bom_header_id', $order->bom_header_id)->get();
            if ($items->isEmpty()) throw new RuntimeException('The assigned BOM has no material items.');

            $code = 'REQ-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(6));
            $requisitionId = DB::table('material_requisitions')->insertGetId([
                'requisition_code' => $code, 'cutsheet_id' => $order->id, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $ledger = app(InventoryLedgerService::class);
            foreach ($items as $item) {
                $materialId = $item->material_id ?: DB::table('materials')->where('internal_code', $item->material_code)->value('id');
                if (!$materialId) {
                    throw new RuntimeException("Material {$item->material_code} does not exist in Material Master.");
                }
                $mappedSizeNames = Schema::hasTable('bom_item_customer_sizes')
                    ? DB::table('bom_item_customer_sizes')
                        ->join('customer_sizes', 'customer_sizes.id', '=', 'bom_item_customer_sizes.customer_size_id')
                        ->where('bom_item_customer_sizes.bom_item_id', $item->id)->pluck('customer_sizes.size_name')
                    : collect();
                $applicableQty = $mappedSizeNames->isEmpty()
                    ? (float) $order->Qty
                    : (float) DB::table('order_sizes')->where('cutsheet_id', $order->id)
                        ->whereIn('size_name', $mappedSizeNames)->sum('quantity');
                $rates = app(NormRateService::class)->forItem($cutsheetId, $item);
                $qty = $applicableQty * $rates['yield'] * (1 + $rates['waste'] / 100);
                $materialColor = DB::table('bom_colorways')->where('bom_item_id', $item->id)
                    ->where('garment_color', $order->Color)->value('material_color') ?? $item->colour;
                $requisitionItemId = DB::table('requisition_items')->insertGetId([
                    'requisition_id' => $requisitionId, 'material_id' => $materialId,
                    'material_color' => $materialColor, 'material_size' => $item->size,
                    'requested_qty' => round($qty, 4), 'issued_qty' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $ledger->reserve([
                    'requisition_item_id' => $requisitionItemId, 'material_id' => $materialId,
                    'color' => $materialColor, 'size' => $item->size, 'quantity' => round($qty, 4),
                ]);
            }
            return $requisitionId;
        });
    }
}
