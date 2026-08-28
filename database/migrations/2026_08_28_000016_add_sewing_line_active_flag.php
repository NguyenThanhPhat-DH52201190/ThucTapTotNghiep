<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sewing_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('sewing_lines', 'is_active')) $table->boolean('is_active')->default(true)->after('labor_rate');
        });
    }

    public function down(): void
    {
        Schema::table('sewing_lines', function (Blueprint $table) {
            if (Schema::hasColumn('sewing_lines', 'is_active')) $table->dropColumn('is_active');
        });
    }
};
