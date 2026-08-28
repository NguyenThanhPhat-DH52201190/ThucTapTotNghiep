<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_balances', 'unit_cost')) {
                $table->decimal('unit_cost', 14, 4)->default(0)->after('balance_qty');
            }
        });

        Schema::table('material_requisitions', function (Blueprint $table) {
            $table->unique('cutsheet_id', 'material_requisitions_cutsheet_unique');
        });

        // Preserve existing legacy opening stock when switching reads to the ledger balance.
        DB::statement("INSERT INTO inventory_balances (material_id, balance_qty, unit_cost, created_at, updated_at)
            SELECT m.id, SUM(i.current_qty), AVG(i.unit_cost), NOW(), NOW()
            FROM inventory_items i INNER JOIN materials m ON m.internal_code = i.material_code
            GROUP BY m.id
            ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)");
    }

    public function down(): void
    {
        Schema::table('material_requisitions', function (Blueprint $table) {
            $table->dropUnique('material_requisitions_cutsheet_unique');
        });
        Schema::table('inventory_balances', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_balances', 'unit_cost')) $table->dropColumn('unit_cost');
        });
    }
};
