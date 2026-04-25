<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            $table->date('Require_date')->nullable()->after('Qty_dis');
            $table->date('Confirm_date')->nullable()->after('Require_date');
        });
    }

    public function down(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            $table->dropColumn(['Require_date', 'Confirm_date']);
        });
    }
};
