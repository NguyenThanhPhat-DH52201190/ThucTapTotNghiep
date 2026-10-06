<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('purchase_orders', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            }
            if (!Schema::hasColumn('purchase_orders', 'vat_percent')) {
                $table->decimal('vat_percent', 5, 2)->default(0)->after('exchange_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $columns = array_filter(['exchange_rate', 'vat_percent'], fn (string $column): bool => Schema::hasColumn('purchase_orders', $column));
            if ($columns) $table->dropColumn($columns);
        });
    }
};
