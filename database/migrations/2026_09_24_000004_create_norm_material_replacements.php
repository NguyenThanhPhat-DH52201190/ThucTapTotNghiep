<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('norm_material_replacements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutsheet_id')->index();
            $table->unsignedBigInteger('bom_header_id');
            $table->unsignedBigInteger('bom_item_id');
            $table->unsignedBigInteger('defect_id')->nullable();
            $table->uuid('submission_key')->unique();
            $table->unsignedBigInteger('material_id')->index();
            $table->json('source_snapshot');
            $table->json('material_snapshot');
            $table->decimal('source_qty', 18, 4);
            $table->decimal('product_qty', 18, 4);
            $table->decimal('yield_confirmed', 18, 4);
            $table->decimal('waste_confirmed', 8, 2);
            $table->decimal('required_qty', 18, 4);
            $table->text('reason');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
        Schema::table('order_material_requirements', function (Blueprint $table) {
            $table->unsignedBigInteger('bom_item_id')->nullable()->change();
            $table->unsignedBigInteger('replacement_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        // Derived rows can be rebuilt; original BOM requirements are retained.
        Illuminate\Support\Facades\DB::table('order_material_requirements')->whereNotNull('replacement_id')->delete();
        Schema::table('order_material_requirements', function (Blueprint $table) {
            $table->dropColumn('replacement_id');
            $table->unsignedBigInteger('bom_item_id')->nullable(false)->change();
        });
        Schema::dropIfExists('norm_material_replacements');
    }
};
