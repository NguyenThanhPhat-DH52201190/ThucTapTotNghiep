<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subcon_contracts', function (Blueprint $table) {
            $table->string('highlight_color', 10)->nullable()->after('customs_declaration_date');
        });
    }

    public function down(): void
    {
        Schema::table('subcon_contracts', function (Blueprint $table) {
            $table->dropColumn('highlight_color');
        });
    }
};
