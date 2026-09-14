<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'bom_headers' => ['total_fabric_cost', 'total_trim_cost', 'total_labor_cost', 'total_cmt'],
        'bom_items' => ['total_cost'],
        'mrp_headers' => ['total_cost'],
        'inventory_transactions' => ['total_cost'],
        'purchase_orders' => ['total_amount'],
        'po_items' => ['total_price'],
        'cost_analyses' => [
            'standard_fabric_cost', 'standard_trim_cost', 'standard_labor_cost', 'standard_cmt_cost',
            'standard_overhead_cost', 'standard_total_cost', 'actual_fabric_cost', 'actual_trim_cost',
            'actual_labor_cost', 'actual_cmt_cost', 'actual_overhead_cost', 'actual_total_cost',
            'revenue_per_unit', 'total_revenue', 'total_profit', 'cost_variance',
        ],
        'order_costings' => [
            'est_material_cost', 'est_labor_cost', 'est_other_cost', 'actual_material_cost',
            'actual_labor_cost', 'actual_other_cost', 'revenue', 'material_variance',
            'labor_variance', 'other_variance', 'total_variance', 'final_profit',
        ],
        'daily_revenues' => ['total_revenue'],
        'daily_production_logs' => ['actual_labor_cost'],
        'order_cost_components' => ['amount'],
    ];

    public function up(): void
    {
        $this->changeScale(4);
        Schema::table('expenses', fn (Blueprint $table) => $table->decimal('amount', 18, 4)->change());
    }

    public function down(): void
    {
        $this->changeScale(2);
        Schema::table('expenses', fn (Blueprint $table) => $table->decimal('amount', 14, 2)->change());
    }

    private function changeScale(int $scale): void
    {
        foreach ($this->columns as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $scale) {
                foreach ($columns as $column) {
                    $table->decimal($column, 18, $scale)->default(0)->change();
                }
            });
        }
    }
};
