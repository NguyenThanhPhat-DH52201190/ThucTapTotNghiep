<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->string('material_color')->nullable();
            $table->string('material_size')->nullable();
            $table->string('lot_roll_no')->nullable();
            $table->string('location')->nullable();
            $table->decimal('balance_qty', 14, 4)->default(0);
            $table->timestamps();

            $table->index(['material_id', 'location']);
            $table->index('lot_roll_no');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'transaction_date')) {
                $table->date('transaction_date')->nullable();
            }
            if (!Schema::hasColumn('inventory_transactions', 'material_id')) {
                $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            }
            if (!Schema::hasColumn('inventory_transactions', 'material_color')) {
                $table->string('material_color')->nullable();
            }
            if (!Schema::hasColumn('inventory_transactions', 'material_size')) {
                $table->string('material_size')->nullable();
            }
            if (!Schema::hasColumn('inventory_transactions', 'lot_roll_no')) {
                $table->string('lot_roll_no')->nullable();
            }
            if (!Schema::hasColumn('inventory_transactions', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('inventory_transactions', 'reference_doc')) {
                $table->string('reference_doc')->nullable();
            }
        });

        Schema::create('material_requisitions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('requisition_code', 50)->unique();
            $table->integer('cutsheet_id');
            $table->foreign('cutsheet_id')->references('id')->on('ocs')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->index(['cutsheet_id', 'status']);
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('requisition_id')->constrained('material_requisitions')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->string('material_color')->nullable();
            $table->string('material_size')->nullable();
            $table->decimal('requested_qty', 14, 4)->default(0);
            $table->decimal('issued_qty', 14, 4)->default(0);
            $table->timestamps();

            $table->index(['requisition_id', 'material_id']);
        });

        Schema::create('material_issues', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('issue_code', 50)->unique();
            $table->foreignId('requisition_id')->constrained('material_requisitions')->restrictOnDelete();
            $table->date('issue_date');
            $table->string('receiver_name')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->index(['requisition_id', 'status']);
        });

        Schema::create('issue_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('issue_id')->constrained('material_issues')->cascadeOnDelete();
            $table->foreignId('requisition_item_id')->constrained('requisition_items')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->string('material_color')->nullable();
            $table->string('material_size')->nullable();
            $table->string('lot_roll_no')->nullable();
            $table->string('location')->nullable();
            $table->decimal('issued_qty', 14, 4)->default(0);
            $table->timestamps();

            $table->index(['issue_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_items');
        Schema::dropIfExists('material_issues');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('material_requisitions');
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_transactions', 'material_id')) $table->dropConstrainedForeignId('material_id');
            foreach (['transaction_date', 'material_color', 'material_size', 'lot_roll_no', 'location', 'reference_doc'] as $column) {
                if (Schema::hasColumn('inventory_transactions', $column)) $table->dropColumn($column);
            }
        });
        Schema::dropIfExists('inventory_balances');
    }
};
