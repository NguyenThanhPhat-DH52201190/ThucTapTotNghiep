<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mtp', 'is_manually_highlighted')) {
            Schema::table('mtp', function (Blueprint $table) {
                $table->boolean('is_manually_highlighted')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mtp', 'is_manually_highlighted')) {
            Schema::table('mtp', function (Blueprint $table) {
                $table->dropColumn('is_manually_highlighted');
            });
        }
    }
};
