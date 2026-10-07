<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_vendors', function (Blueprint $table): void {
            if (!Schema::hasColumn('material_vendors', 'supplier_description')) {
                $table->string('supplier_description', 500)->nullable();
            }
            if (!Schema::hasColumn('material_vendors', 'supplier_color_code')) {
                $table->string('supplier_color_code', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_vendors', function (Blueprint $table): void {
            $columns = array_filter(['supplier_description', 'supplier_color_code'],
                fn (string $column): bool => Schema::hasColumn('material_vendors', $column));
            if ($columns) $table->dropColumn($columns);
        });
    }
};
