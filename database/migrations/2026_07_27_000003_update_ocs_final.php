<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocs', function (Blueprint $table) {
            // Chỉ thêm các cột chưa có
            if (!Schema::hasColumn('ocs', 'status')) {
                $table->string('status', 30)->default('pending')
                      ->after('Qty')
                      ->comment('pending|confirmed|in_production|completed|cancelled');
            }
            if (!Schema::hasColumn('ocs', 'bom_header_id')) {
                $table->unsignedBigInteger('bom_header_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('ocs', 'expected_ship_date')) {
                $table->date('expected_ship_date')->nullable()->after('bom_header_id');
            }
            if (!Schema::hasColumn('ocs', 'priority')) {
                $table->string('priority', 20)->default('medium')
                      ->after('expected_ship_date')
                      ->comment('low|medium|high|urgent');
            }
            if (!Schema::hasColumn('ocs', 'order_notes')) {
                $table->text('order_notes')->nullable()->after('priority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ocs', function (Blueprint $table) {
            $columns = ['status', 'bom_header_id', 'expected_ship_date', 'priority', 'order_notes'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('ocs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
