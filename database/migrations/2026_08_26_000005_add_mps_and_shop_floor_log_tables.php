<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sewing_lines', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('line_code', 50)->unique();
            $table->string('line_name');
            $table->unsignedInteger('default_workers')->default(0);
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('wo_number', 50)->unique();
            $table->integer('cutsheet_id');
            $table->foreign('cutsheet_id')->references('id')->on('ocs')->restrictOnDelete();
            $table->string('status', 30)->default('planned');
            $table->timestamps();

            $table->index(['cutsheet_id', 'status']);
        });

        Schema::create('mps_schedules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('sewing_lines')->restrictOnDelete();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->unsignedInteger('daily_target_qty')->default(0);
            $table->string('material_eta_status', 30)->default('unknown');
            $table->timestamps();

            $table->index(['line_id', 'planned_start_date']);
            $table->index('material_eta_status');
        });

        Schema::create('daily_production_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->date('log_date');
            $table->foreignId('mps_schedule_id')->constrained('mps_schedules')->cascadeOnDelete();
            $table->string('operation_stage', 30);
            $table->unsignedInteger('target_qty')->default(0);
            $table->unsignedInteger('actual_qty')->default(0);
            $table->unsignedInteger('defect_qty')->default(0);
            $table->unsignedInteger('actual_workers')->default(0);
            $table->decimal('working_hours', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['mps_schedule_id', 'log_date', 'operation_stage'], 'daily_production_log_unique');
        });

        Schema::create('production_log_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('log_id')->constrained('daily_production_logs')->cascadeOnDelete();
            $table->string('color')->nullable();
            $table->string('size_name')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('downtime_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->date('log_date');
            $table->foreignId('line_id')->constrained('sewing_lines')->restrictOnDelete();
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['line_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downtime_logs');
        Schema::dropIfExists('production_log_details');
        Schema::dropIfExists('daily_production_logs');
        Schema::dropIfExists('mps_schedules');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('sewing_lines');
    }
};
