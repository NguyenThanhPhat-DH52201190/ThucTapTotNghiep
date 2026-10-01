<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            if (!Schema::hasColumn('mtp', 'fabric_issue_date')) {
                $table->date('fabric_issue_date')->nullable();
            }
            if (!Schema::hasColumn('mtp', 'trims_issue_date')) {
                $table->date('trims_issue_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            foreach (['fabric_issue_date', 'trims_issue_date'] as $column) {
                if (Schema::hasColumn('mtp', $column)) $table->dropColumn($column);
            }
        });
    }
};
