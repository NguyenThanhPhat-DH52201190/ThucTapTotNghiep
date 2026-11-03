<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subcon_contracts', function (Blueprint $table) {
            $table->string('processing_stage', 191)->nullable()->after('tax_code');
        });
    }

    public function down(): void
    {
        Schema::table('subcon_contracts', function (Blueprint $table) {
            $table->dropColumn('processing_stage');
        });
    }
};
