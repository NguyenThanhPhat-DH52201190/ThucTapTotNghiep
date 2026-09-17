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
            if (!$bom || $bom->status !== 'active' || trim($bom->style_no) !== trim($order->SNo)) throw new RuntimeException('Confirmation requires an active BOM that matches the order style.');
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
                    $materialId = DB::table('materials')->insertGetId([
                        'internal_code' => $item->material_code, 'material_name' => $item->material_name,
                        'color' => $item->colour, 'size' => $item->size, 'unit' => $item->unit,
                        'material_type' => $item->material_type, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('bom_items')->where('id', $item->id)->update(['material_id' => $materialId, 'updated_at' => now()]);
                }
                $applicableQty = $order->Qty;
                if (($bom->bom_kind ?? 'template') === 'order' && Schema::hasTable('bom_item_size_mappings')) {
                    $applicableQty = DB::table('bom_item_size_mappings')
                        ->join('order_sizes', 'bom_item_size_mappings.order_size_id', '=', 'order_sizes.id')
                        ->where('bom_item_size_mappings.bom_item_id', $item->id)
                        ->where('order_sizes.cutsheet_id', $order->id)->sum('order_sizes.quantity');
                }
                $qty = $applicableQty * $item->consumption_rate * (1 + ($item->waste_percent / 100));
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
