<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('development_norm_items', 'is_hidden')) {
            Schema::table('development_norm_items', function (Blueprint $table) {
                $table->boolean('is_hidden')->default(false)->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('development_norm_items', 'is_hidden')) {
            Schema::table('development_norm_items', function (Blueprint $table) {
                $table->dropColumn('is_hidden');
            });
        }
    }
};
