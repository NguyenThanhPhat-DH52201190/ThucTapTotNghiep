<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_item_customer_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_item_id')->constrained('bom_items')->cascadeOnDelete();
            $table->foreignId('customer_size_id')->constrained('customer_sizes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['bom_item_id', 'customer_size_id'], 'bom_item_customer_size_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_item_customer_sizes');
    }
};
