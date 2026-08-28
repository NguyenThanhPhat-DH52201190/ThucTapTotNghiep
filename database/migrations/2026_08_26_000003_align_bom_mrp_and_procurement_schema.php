<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('bom_headers', 'style_id')) {
                $table->foreignId('style_id')->nullable()->constrained('styles')->nullOnDelete();
            }
        });

        Schema::table('bom_items', function (Blueprint $table) {
            if (!Schema::hasColumn('bom_items', 'material_id')) {
                $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            }
        });

        Schema::create('mrp_suggestions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('mrp_header_id')->nullable()->constrained('mrp_headers')->cascadeOnDelete();
            $table->integer('cutsheet_id')->nullable();
            $table->foreign('cutsheet_id')->references('id')->on('ocs')->nullOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->string('material_color')->nullable();
            $table->decimal('gross_qty', 14, 4)->default(0);
            $table->decimal('available_qty', 14, 4)->default(0);
            $table->decimal('net_qty', 14, 4)->default(0);
            $table->date('required_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();

            $table->index(['cutsheet_id', 'status']);
            $table->index(['material_id', 'required_date']);
        });

        Schema::table('mrp_items', function (Blueprint $table) {
            if (!Schema::hasColumn('mrp_items', 'material_id')) {
                $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            }
            if (!Schema::hasColumn('mrp_items', 'material_color')) {
                $table->string('material_color')->nullable();
            }
            if (!Schema::hasColumn('mrp_items', 'required_date')) {
                $table->date('required_date')->nullable();
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'currency')) {
                $table->string('currency', 3)->default('VND');
            }
        });

        Schema::table('po_items', function (Blueprint $table) {
            if (!Schema::hasColumn('po_items', 'mrp_suggestion_id')) {
                $table->foreignId('mrp_suggestion_id')->nullable()
                    ->constrained('mrp_suggestions')->nullOnDelete();
            }
            if (!Schema::hasColumn('po_items', 'material_id')) {
                $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            }
            if (!Schema::hasColumn('po_items', 'color')) {
                $table->string('color')->nullable();
            }
        });

        Schema::table('po_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('po_receipts', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->constrained('suppliers')->nullOnDelete();
            }
            if (!Schema::hasColumn('po_receipts', 'status')) {
                $table->string('status', 30)->default('received');
            }
        });

        Schema::table('po_receipt_items', function (Blueprint $table) {
            if (!Schema::hasColumn('po_receipt_items', 'material_id')) {
                $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('po_receipt_items', function (Blueprint $table) {
            if (Schema::hasColumn('po_receipt_items', 'material_id')) $table->dropConstrainedForeignId('material_id');
        });
        Schema::table('po_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('po_receipts', 'vendor_id')) $table->dropConstrainedForeignId('vendor_id');
            if (Schema::hasColumn('po_receipts', 'status')) $table->dropColumn('status');
        });
        Schema::table('po_items', function (Blueprint $table) {
            if (Schema::hasColumn('po_items', 'mrp_suggestion_id')) $table->dropConstrainedForeignId('mrp_suggestion_id');
            if (Schema::hasColumn('po_items', 'material_id')) $table->dropConstrainedForeignId('material_id');
            if (Schema::hasColumn('po_items', 'color')) $table->dropColumn('color');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'currency')) $table->dropColumn('currency');
        });
        Schema::table('mrp_items', function (Blueprint $table) {
            if (Schema::hasColumn('mrp_items', 'material_id')) $table->dropConstrainedForeignId('material_id');
            if (Schema::hasColumn('mrp_items', 'material_color')) $table->dropColumn('material_color');
            if (Schema::hasColumn('mrp_items', 'required_date')) $table->dropColumn('required_date');
        });
        Schema::dropIfExists('mrp_suggestions');
        Schema::table('bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('bom_items', 'material_id')) $table->dropConstrainedForeignId('material_id');
        });
        Schema::table('bom_headers', function (Blueprint $table) {
            if (Schema::hasColumn('bom_headers', 'style_id')) $table->dropConstrainedForeignId('style_id');
        });
    }
};
