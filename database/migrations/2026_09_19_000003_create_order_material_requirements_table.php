<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutsheet_id');
            $table->unsignedBigInteger('bom_header_id');
            $table->unsignedBigInteger('bom_item_id');
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('material_code');
            $table->string('material_name');
            $table->string('material_type', 50)->nullable();
            $table->string('material_color')->nullable();
            $table->string('material_size')->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('product_qty', 18, 4)->default(0);
            $table->decimal('consumption_rate', 18, 4)->default(0);
            $table->decimal('waste_percent', 8, 2)->default(0);
            $table->decimal('required_qty', 18, 4)->default(0);
            $table->decimal('on_hand_qty', 18, 4)->default(0);
            $table->decimal('reserved_qty', 18, 4)->default(0);
            $table->decimal('available_qty', 18, 4)->default(0);
            $table->decimal('shortage_qty', 18, 4)->default(0);
            $table->string('stock_status', 30)->default('shortage');
            $table->timestamps();
            $table->unique(['cutsheet_id', 'bom_item_id'], 'order_material_requirement_unique');
            $table->index(['cutsheet_id', 'material_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_material_requirements');
    }
};
