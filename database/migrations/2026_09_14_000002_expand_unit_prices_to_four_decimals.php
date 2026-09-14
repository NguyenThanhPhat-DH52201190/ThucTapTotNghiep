<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'bom_items' => 'unit_cost',
        'inventory_items' => 'unit_cost',
        'inventory_transactions' => 'unit_cost',
        'inventory_balances' => 'unit_cost',
        'po_items' => 'unit_price',
        'material_vendors' => 'unit_price',
        'daily_revenues' => 'unit_price',
        'ocs' => 'unit_price',
    ];

    public function up(): void
    {
        foreach ($this->columns as $tableName => $column) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $table->decimal($column, 14, 4)->default(0)->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $tableName => $column) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $table->decimal($column, 14, 2)->default(0)->change();
                });
            }
        }
    }
};
