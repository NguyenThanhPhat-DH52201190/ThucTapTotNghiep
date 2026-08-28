<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->engine = 'InnoDB'; $table->id();
            $table->string('count_code')->unique(); $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable(); $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable(); $table->timestamp('approved_at')->nullable(); $table->timestamps();
        });
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->engine = 'InnoDB'; $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->decimal('system_qty', 14, 4); $table->decimal('counted_qty', 14, 4);
            $table->text('reason')->nullable(); $table->timestamps();
            $table->unique(['stock_count_id', 'inventory_balance_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_count_items'); Schema::dropIfExists('stock_counts'); }
};
