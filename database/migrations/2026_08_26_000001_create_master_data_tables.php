<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_info', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('contact')->nullable();
            $table->string('tel', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('brand')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('email');
        });

        Schema::create('styles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('style_no')->unique();
            $table->string('style_name');
            $table->string('image_url')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('internal_code')->unique();
            $table->string('material_name');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('unit', 20)->default('M');
            $table->string('material_type', 30)->default('fabric');
            $table->timestamps();

            $table->index(['color', 'size']);
            $table->index('material_type');
        });

        Schema::create('material_vendors', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('vendor_item_code')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->boolean('is_default_vendor')->default(false);
            $table->timestamps();

            $table->unique(['material_id', 'vendor_id']);
            $table->index(['material_id', 'is_default_vendor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_vendors');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('styles');
        Schema::dropIfExists('customer_info');
    }
};
