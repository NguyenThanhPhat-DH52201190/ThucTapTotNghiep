<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderCostSnapshotService
{
    public function snapshot(int $cutsheetId): void
    {
        DB::transaction(function () use ($cutsheetId) {
            if (DB::table('order_costings')->where('cutsheet_id', $cutsheetId)->exists()) return;
            $order = DB::table('ocs')->where('id', $cutsheetId)->first();
            if (!$order) return;
            $estimatedMaterial = (float) DB::table('bom_items')->where('bom_header_id', $order->bom_header_id)
                ->sum(DB::raw('total_cost * ' . (int) $order->Qty));
            $actualMaterial = abs((float) DB::table('inventory_transactions')
                ->join('material_issues', function ($join) { $join->on('inventory_transactions.reference_id', '=', 'material_issues.id')->where('inventory_transactions.reference_type', '=', 'MATERIAL_ISSUE'); })
                ->join('material_requisitions', 'material_issues.requisition_id', '=', 'material_requisitions.id')
                ->where('material_requisitions.cutsheet_id', $cutsheetId)
                ->sum('inventory_transactions.total_cost'));
            if (($order->material_ownership ?? 'factory') === 'customer') { $estimatedMaterial = 0; $actualMaterial = 0; }
            $estimatedLabor = (float) $order->CMT * $order->Qty;
            // Labor is captured from actual worker-hours × approved hourly rate in SFC.
            // Do not substitute estimated CMT when no shop-floor data was recorded.
            $actualLabor = (float) DB::table('daily_production_logs')
                ->join('mps_schedules', 'daily_production_logs.mps_schedule_id', '=', 'mps_schedules.id')
                ->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')
                ->where('work_orders.cutsheet_id', $cutsheetId)->sum('daily_production_logs.actual_labor_cost');
            $estimatedOther = (float) DB::table('order_cost_components')->where('cutsheet_id', $cutsheetId)->where('cost_stage', 'estimated')->sum('amount');
            $actualOther = (float) DB::table('order_cost_components')->where('cutsheet_id', $cutsheetId)->where('cost_stage', 'actual')->sum('amount');
            $revenue = ($order->order_type ?? 'cmt') === 'fob' ? (float) $order->unit_price * $order->Qty : (float) $order->CMT * $order->Qty;
            DB::table('order_costings')->insert([
                'cutsheet_id' => $cutsheetId, 'est_material_cost' => $estimatedMaterial,
                'est_labor_cost' => $estimatedLabor, 'est_other_cost' => $estimatedOther, 'actual_material_cost' => $actualMaterial,
                'actual_labor_cost' => $actualLabor, 'actual_other_cost' => $actualOther, 'revenue' => $revenue,
                'material_variance' => $actualMaterial - $estimatedMaterial,
                'labor_variance' => $actualLabor - $estimatedLabor,
                'other_variance' => $actualOther - $estimatedOther,
                'total_variance' => ($actualMaterial + $actualLabor + $actualOther) - ($estimatedMaterial + $estimatedLabor + $estimatedOther),
                'final_profit' => $revenue - $actualMaterial - $actualLabor - $actualOther,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
