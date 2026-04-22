<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            $table->date('Norm_date')->nullable()->after('Trim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            $table->dropColumn('Norm_date');
        });
    }
};
