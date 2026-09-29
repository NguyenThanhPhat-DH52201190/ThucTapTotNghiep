<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentNormSnapshotService
{
    /** Copy the assigned BOM once; all later Development edits stay in the copied tables. */
    public function copyFromOrder(int $cutsheetId): int
    {
        $order = DB::table('ocs')->where('id', $cutsheetId)->lockForUpdate()->first();
        if (!$order || !$order->bom_header_id) {
            throw new RuntimeException('An OCS with an assigned BOM is required to create its Development norm.');
        }

        $existingId = DB::table('development_norms')->where('cutsheet_id', $cutsheetId)->value('id');
        if ($existingId) return (int) $existingId;

        $bom = DB::table('bom_headers')->where('id', $order->bom_header_id)->first();
        if (!$bom) throw new RuntimeException('The assigned BOM could not be found.');

        $now = now();
        $normId = DB::table('development_norms')->insertGetId([
            'cutsheet_id' => $order->id,
            'bom_header_id' => $bom->id,
            'cs' => $order->CS,
            'style_no' => $order->SNo,
            'style_name' => $order->Sname,
            'customer' => $order->Customer,
            'product_qty' => $order->Qty,
            'copied_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderSizes = DB::table('order_sizes')->where('cutsheet_id', $cutsheetId)->orderBy('id')->get();
        if ($orderSizes->isEmpty()) {
            $orderSizes = collect([(object) ['size_name' => 'ONE SIZE', 'quantity' => $order->Qty]]);
        }
        $developmentSizes = collect();
        foreach ($orderSizes as $index => $size) {
            $sizeId = DB::table('development_norm_sizes')->insertGetId([
                'development_norm_id' => $normId, 'size_name' => $size->size_name,
                'quantity' => $size->quantity, 'sort_order' => $index,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $developmentSizes->push((object) ['id' => $sizeId, 'quantity' => $size->quantity]);
        }

        $items = DB::table('bom_items as item')
            ->leftJoin('materials as material', 'material.id', '=', 'item.material_id')
            ->where('item.bom_header_id', $bom->id)
            ->orderBy('item.sort_order')->orderBy('item.id')
            ->get(['item.id', 'item.material_id', 'item.material_code', 'material.old_code as material_old_code',
                'item.material_name', 'item.material_type', 'item.colour', 'item.size', 'item.width', 'item.unit',
                'item.consumption_rate', 'item.waste_percent', 'item.remark', 'item.sort_order']);

        foreach ($items as $item) {
            $developmentItemId = DB::table('development_norm_items')->insertGetId([
                'development_norm_id' => $normId,
                'bom_item_id' => $item->id,
                'material_id' => $item->material_id,
                'material_code' => $item->material_code,
                'material_old_code' => $item->material_old_code,
                'material_name' => $item->material_name,
                'material_type' => $item->material_type,
                'colour' => $item->colour,
                'size' => $item->size,
                'width' => $item->width,
                'unit' => $item->unit,
                'source_yield' => $item->consumption_rate,
                'source_waste_percent' => $item->waste_percent,
                'yield_value' => $item->consumption_rate,
                'waste_percent' => $item->waste_percent,
                'remark' => $item->remark,
                'sort_order' => $item->sort_order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($developmentSizes as $size) {
                DB::table('development_norm_item_sizes')->insert([
                    'development_norm_item_id' => $developmentItemId, 'development_norm_size_id' => $size->id,
                    'yield_value' => $item->consumption_rate, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        return (int) $normId;
    }
}
