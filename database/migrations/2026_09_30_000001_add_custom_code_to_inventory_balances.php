<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('inventory_balances', 'custom_code')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->string('custom_code', 191)->nullable()->after('location_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_balances', 'custom_code')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->dropColumn('custom_code');
            });
        }
    }
};
