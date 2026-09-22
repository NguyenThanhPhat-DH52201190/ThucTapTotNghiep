<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderMaterialRequirementService
{
    public function sync(int $cutsheetId): Collection
    {
        $order = DB::table('ocs')->find($cutsheetId);
        if (!$order || !$order->bom_header_id) {
            throw new RuntimeException('Select a BOM before calculating material requirements.');
        }

        $items = DB::table('bom_items')->where('bom_header_id', $order->bom_header_id)->orderBy('sort_order')->get();
        $balances = DB::table('inventory_balances')
            ->whereIn('material_id', $items->pluck('material_id')->filter()->unique())->get();
        $activeItemIds = [];

        foreach ($items as $item) {
            $activeItemIds[] = $item->id;
            $mappedSizeNames = DB::table('bom_item_customer_sizes')
                ->join('customer_sizes', 'customer_sizes.id', '=', 'bom_item_customer_sizes.customer_size_id')
                ->where('bom_item_customer_sizes.bom_item_id', $item->id)->pluck('customer_sizes.size_name');
            $productQty = $mappedSizeNames->isEmpty()
                ? (float) $order->Qty
                : (float) DB::table('order_sizes')->where('cutsheet_id', $order->id)
                    ->whereIn('size_name', $mappedSizeNames)->sum('quantity');

            $materialColor = DB::table('bom_colorways')->where('bom_item_id', $item->id)
                ->where('garment_color', $order->Color)->value('material_color') ?? $item->colour;
            $matching = $balances->where('material_id', $item->material_id);
            if (trim((string) $materialColor) !== '') {
                $matching = $matching->filter(fn ($balance) => strcasecmp(trim((string) $balance->material_color), trim((string) $materialColor)) === 0);
            }
            if (trim((string) $item->size) !== '') {
                $matching = $matching->filter(fn ($balance) => strcasecmp(trim((string) $balance->material_size), trim((string) $item->size)) === 0);
            }

            $rates = app(NormRateService::class)->forItem($cutsheetId, $item);
            $required = $productQty * $rates['yield'] * (1 + $rates['waste'] / 100);
            $onHand = (float) $matching->sum('balance_qty');
            $reserved = (float) $matching->sum('reserved_qty');
            $available = max(0, $onHand - $reserved);
            $shortage = max(0, $required - $available);

            DB::table('order_material_requirements')->updateOrInsert(
                ['cutsheet_id' => $order->id, 'bom_item_id' => $item->id],
                [
                    'bom_header_id' => $order->bom_header_id, 'material_id' => $item->material_id,
                    'material_code' => $item->material_code, 'material_name' => $item->material_name,
                    'material_type' => $item->material_type, 'material_color' => $materialColor,
                    'material_size' => $item->size, 'unit' => $item->unit,
                    'product_qty' => round($productQty, 4), 'consumption_rate' => $rates['yield'],
                    'waste_percent' => $rates['waste'], 'required_qty' => round($required, 4),
                    'on_hand_qty' => round($onHand, 4), 'reserved_qty' => round($reserved, 4),
                    'available_qty' => round($available, 4), 'shortage_qty' => round($shortage, 4),
                    'stock_status' => $shortage > 0 ? 'shortage' : 'sufficient',
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }

        $obsolete = DB::table('order_material_requirements')->where('cutsheet_id', $cutsheetId);
        $activeItemIds ? $obsolete->whereNotIn('bom_item_id', $activeItemIds)->delete() : $obsolete->delete();

        return DB::table('order_material_requirements')->where('cutsheet_id', $cutsheetId)->orderBy('id')->get();
    }
}
