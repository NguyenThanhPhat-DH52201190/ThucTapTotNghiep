<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name')->unique();
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('material_subcategories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('category_id')->constrained('material_categories')->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['category_id', 'name']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('material_type')->constrained('material_categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->after('category_id')->constrained('material_subcategories')->nullOnDelete();
        });

        $now = now();
        $types = DB::table('materials')->select('material_type')->distinct()->pluck('material_type')
            ->filter(fn ($type) => trim((string) $type) !== '');

        foreach ($types as $type) {
            $name = Str::headline((string) $type);
            $slug = Str::slug((string) $type);
            $categoryId = DB::table('material_categories')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('materials')->where('material_type', $type)->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcategory_id');
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('material_subcategories');
        Schema::dropIfExists('material_categories');
    }
};
