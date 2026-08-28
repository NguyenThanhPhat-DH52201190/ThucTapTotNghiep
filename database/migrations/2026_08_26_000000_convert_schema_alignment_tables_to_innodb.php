<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'suppliers', 'bom_headers', 'bom_items', 'mrp_headers', 'mrp_items',
        'purchase_orders', 'po_items', 'po_receipts', 'po_receipt_items',
        'inventory_transactions', 'daily_revenues',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
            }
        }
    }

    public function down(): void
    {
        // Engine conversion is intentionally not reversed: restoring MyISAM
        // would disable the foreign-key integrity introduced by this series.
    }
};
