<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrp_lot_allocations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('mrp_suggestion_id')->constrained('mrp_suggestions')->cascadeOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->decimal('allocated_qty', 14, 4);
            $table->timestamps();
            $table->unique(['mrp_suggestion_id', 'inventory_balance_id'], 'mrp_suggestion_balance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrp_lot_allocations');
    }
};
