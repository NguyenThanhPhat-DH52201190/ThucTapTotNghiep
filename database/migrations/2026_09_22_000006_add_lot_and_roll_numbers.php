<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['po_receipt_items', 'inventory_balances', 'inventory_transactions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('lot_no', 80)->nullable();
                $table->string('roll_no', 80)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['po_receipt_items', 'inventory_balances', 'inventory_transactions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['lot_no', 'roll_no']));
        }
    }
};
