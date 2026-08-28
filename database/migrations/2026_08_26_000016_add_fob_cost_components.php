<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL leaves this table behind when an earlier FK creation fails.
        // This migration owns the table and has not been recorded as run yet.
        if (Schema::hasTable('order_cost_components')) Schema::drop('order_cost_components');
        Schema::create('order_cost_components', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->integer('cutsheet_id');
            $table->foreign('cutsheet_id')->references('id')->on('ocs')->restrictOnDelete();
            $table->string('cost_type', 30); // freight | qc | packing | overhead | other
            $table->string('cost_stage', 15); // estimated | actual
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('note')->nullable();
            // Legacy users table is MyISAM, so it cannot participate in a MySQL FK.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['cutsheet_id', 'cost_stage']);
        });

        Schema::table('order_costings', function (Blueprint $table) {
            if (!Schema::hasColumn('order_costings', 'est_other_cost')) $table->decimal('est_other_cost', 14, 2)->default(0)->after('est_labor_cost');
            if (!Schema::hasColumn('order_costings', 'actual_other_cost')) $table->decimal('actual_other_cost', 14, 2)->default(0)->after('actual_labor_cost');
            if (!Schema::hasColumn('order_costings', 'other_variance')) $table->decimal('other_variance', 14, 2)->default(0)->after('labor_variance');
        });
    }

    public function down(): void
    {
        Schema::table('order_costings', function (Blueprint $table) {
            $table->dropColumn(['est_other_cost', 'actual_other_cost', 'other_variance']);
        });
        Schema::dropIfExists('order_cost_components');
    }
};
