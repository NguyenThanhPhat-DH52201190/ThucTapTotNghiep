<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            // Trạng thái sản xuất
            if (!Schema::hasColumn('mtp', 'mps_status')) {
                $table->string('mps_status', 30)->default('planned')
                      ->after('Confirm_date')
                      ->comment('planned|in_production|completed|on_hold');
            }

            // Kế hoạch Cắt & May
            if (!Schema::hasColumn('mtp', 'planned_cut_start')) {
                $table->date('planned_cut_start')->nullable()->after('mps_status');
            }
            if (!Schema::hasColumn('mtp', 'planned_cut_end')) {
                $table->date('planned_cut_end')->nullable()->after('planned_cut_start');
            }
            if (!Schema::hasColumn('mtp', 'planned_sew_start')) {
                $table->date('planned_sew_start')->nullable()->after('planned_cut_end');
            }
            if (!Schema::hasColumn('mtp', 'planned_sew_end')) {
                $table->date('planned_sew_end')->nullable()->after('planned_sew_start');
            }

            // Ngày Cắt & May thực tế (kết nối với Shop Floor Control)
            if (!Schema::hasColumn('mtp', 'actual_cut_start')) {
                $table->date('actual_cut_start')->nullable()->after('planned_sew_end');
            }
            if (!Schema::hasColumn('mtp', 'actual_cut_end')) {
                $table->date('actual_cut_end')->nullable()->after('actual_cut_start');
            }
            if (!Schema::hasColumn('mtp', 'actual_sew_start')) {
                $table->date('actual_sew_start')->nullable()->after('actual_cut_end');
            }
            if (!Schema::hasColumn('mtp', 'actual_sew_end')) {
                $table->date('actual_sew_end')->nullable()->after('actual_sew_start');
            }

            // Độ ưu tiên & ghi chú
            if (!Schema::hasColumn('mtp', 'mps_priority')) {
                $table->string('mps_priority', 20)->default('medium')
                      ->after('actual_sew_end')
                      ->comment('low|medium|high|urgent');
            }
            if (!Schema::hasColumn('mtp', 'mps_notes')) {
                $table->text('mps_notes')->nullable()->after('mps_priority');
            }

            // Sản lượng kế hoạch mỗi ngày (dùng cho Shop Floor)
            if (!Schema::hasColumn('mtp', 'daily_target_qty')) {
                $table->integer('daily_target_qty')->nullable()->after('mps_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            $columns = [
                'mps_status', 'planned_cut_start', 'planned_cut_end',
                'planned_sew_start', 'planned_sew_end',
                'actual_cut_start', 'actual_cut_end',
                'actual_sew_start', 'actual_sew_end',
                'mps_priority', 'mps_notes', 'daily_target_qty',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('mtp', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
