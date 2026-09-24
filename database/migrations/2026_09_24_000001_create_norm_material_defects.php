<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('norm_material_defects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutsheet_id')->index();
            $table->uuid('submission_key');
            $table->unsignedInteger('line_no');
            $table->unsignedBigInteger('bom_item_id');
            $table->unsignedBigInteger('material_id')->nullable();
            foreach (['material_code', 'material_name', 'material_color', 'material_size', 'unit'] as $field) $table->string($field)->nullable();
            $table->date('occurred_on');
            $table->decimal('defect_qty', 18, 4);
            $table->decimal('replacement_qty', 18, 4)->default(0);
            $table->string('disposition', 30);
            $table->text('reason');
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['cutsheet_id', 'submission_key', 'line_no'], 'norm_defect_submission_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('norm_material_defects'); }
};
