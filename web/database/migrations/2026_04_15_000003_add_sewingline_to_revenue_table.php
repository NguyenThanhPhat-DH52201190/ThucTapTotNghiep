<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('revenue', 'SewingLine')) {
            Schema::table('revenue', function (Blueprint $table) {
                $table->string('SewingLine')->nullable()->after('CS');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('revenue', 'SewingLine')) {
            Schema::table('revenue', function (Blueprint $table) {
                $table->dropColumn('SewingLine');
            });
        }
    }
};
