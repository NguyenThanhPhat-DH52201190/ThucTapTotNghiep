<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['material_categories', 'material_subcategories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('image_path')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['material_categories', 'material_subcategories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('image_path'));
        }
    }
};
