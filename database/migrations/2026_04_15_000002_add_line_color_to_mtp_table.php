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
            $table->string('LineColor', 7)->nullable()->default('#808080')->after('Line');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            $table->dropColumn('LineColor');
        });
    }
};
