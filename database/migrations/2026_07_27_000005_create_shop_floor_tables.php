<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production Orders - Liên kết với MTP, theo dõi từng công đoạn
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mtp_id')->nullable();           // FK to mtp
            $table->string('cu');                                        // CS code from mtp
            $table->string('line');                                      // Production line
            $table->string('operation', 30)->default('sewing');          // cut | sewing | finishing
            $table->integer('planned_qty')->default(0);
            $table->integer('actual_qty')->default(0);
            $table->integer('defect_qty')->default(0);
            $table->integer('wip_qty')->default(0);                      // Work In Progress
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('not_started');        // not_started | in_progress | completed | on_hold
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['mtp_id', 'operation']);
            $table->index('status');
        });

        // Daily Production - Sản lượng mỗi ngày
        Schema::create('daily_production', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id');
            $table->date('production_date');
            $table->string('operation', 30);                             // cut | sewing | finishing
            $table->integer('target_qty')->default(0);
            $table->integer('actual_qty')->default(0);
            $table->integer('defect_qty')->default(0);
            $table->integer('downtime_minutes')->default(0);
            $table->decimal('efficiency', 5, 2)->default(0);            // Hiệu suất %
            $table->integer('operator_count')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('production_order_id')
                  ->references('id')->on('production_orders')
                  ->cascadeOnDelete();

            $table->index(['production_date', 'operation']);
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_production');
        Schema::dropIfExists('production_orders');
    }
};
