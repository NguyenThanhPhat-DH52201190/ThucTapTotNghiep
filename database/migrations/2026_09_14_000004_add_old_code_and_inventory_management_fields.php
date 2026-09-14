<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('materials', 'old_code')) {
            Schema::table('materials', fn (Blueprint $table) => $table->string('old_code', 191)->nullable()->unique()->after('internal_code'));
        }
        Schema::table('inventory_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_balances', 'min_stock_level')) $table->decimal('min_stock_level', 14, 4)->default(0)->after('reserved_qty');
            if (!Schema::hasColumn('inventory_balances', 'reorder_point')) $table->decimal('reorder_point', 14, 4)->default(0)->after('min_stock_level');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_balances', 'min_stock_level')) $table->dropColumn('min_stock_level');
            if (Schema::hasColumn('inventory_balances', 'reorder_point')) $table->dropColumn('reorder_point');
        });
        if (Schema::hasColumn('materials', 'old_code')) {
            Schema::table('materials', fn (Blueprint $table) => $table->dropUnique(['old_code']));
            Schema::table('materials', fn (Blueprint $table) => $table->dropColumn('old_code'));
        }
    }
};
