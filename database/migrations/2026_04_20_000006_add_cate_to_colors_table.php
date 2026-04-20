<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('colors', 'cate')) {
            Schema::table('colors', function (Blueprint $table): void {
                $table->string('cate', 10)->default('GSV')->after('hex_code');
            });
        }

        DB::table('colors')
            ->whereNull('cate')
            ->update(['cate' => 'GSV']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('colors', 'cate')) {
            Schema::table('colors', function (Blueprint $table): void {
                $table->dropColumn('cate');
            });
        }
    }
};
