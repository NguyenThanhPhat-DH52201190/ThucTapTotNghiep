<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_costings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->integer('cutsheet_id');
            $table->foreign('cutsheet_id')->references('id')->on('ocs')->restrictOnDelete();
            $table->decimal('est_material_cost', 14, 2)->default(0);
            $table->decimal('est_labor_cost', 14, 2)->default(0);
            $table->decimal('actual_material_cost', 14, 2)->default(0);
            $table->decimal('actual_labor_cost', 14, 2)->default(0);
            $table->decimal('final_profit', 14, 2)->default(0);
            $table->timestamps();

            $table->unique('cutsheet_id');
        });

        Schema::table('daily_revenues', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_revenues', 'report_date')) {
                $table->date('report_date')->nullable();
            }
            if (!Schema::hasColumn('daily_revenues', 'line_id')) {
                $table->foreignId('line_id')->nullable()->constrained('sewing_lines')->nullOnDelete();
            }
            if (!Schema::hasColumn('daily_revenues', 'cutsheet_id')) {
                $table->integer('cutsheet_id')->nullable();
                $table->foreign('cutsheet_id')->references('id')->on('ocs')->nullOnDelete();
            }
            if (!Schema::hasColumn('daily_revenues', 'completed_qty')) {
                $table->unsignedInteger('completed_qty')->default(0);
            }
            if (!Schema::hasColumn('daily_revenues', 'unit_price')) {
                $table->decimal('unit_price', 14, 2)->default(0);
            }
            if (!Schema::hasColumn('daily_revenues', 'total_revenue')) {
                $table->decimal('total_revenue', 14, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_revenues', function (Blueprint $table) {
            if (Schema::hasColumn('daily_revenues', 'line_id')) $table->dropConstrainedForeignId('line_id');
            if (Schema::hasColumn('daily_revenues', 'cutsheet_id')) $table->dropConstrainedForeignId('cutsheet_id');
            foreach (['report_date', 'completed_qty', 'unit_price', 'total_revenue'] as $column) {
                if (Schema::hasColumn('daily_revenues', $column)) $table->dropColumn($column);
            }
        });
        Schema::dropIfExists('order_costings');
    }
};
