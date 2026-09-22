<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id')->unique();
            $table->decimal('opening_qty', 18, 4);
            $table->unsignedBigInteger('opening_transaction_id')->default(0);
            $table->timestamp('opened_at');
            $table->unsignedInteger('sort_order')->default(1000)->index();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        Schema::create('stock_record_priorities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_record_id');
            $table->unsignedBigInteger('cutsheet_id');
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->unique(['stock_record_id', 'cutsheet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_record_priorities');
        Schema::dropIfExists('stock_records');
    }
};
