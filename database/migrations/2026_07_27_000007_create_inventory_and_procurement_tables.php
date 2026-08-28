<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === INVENTORY TABLES ===
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('type', 30)->default('raw_material');  // raw_material | wip | finished_goods
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('material_code', 100);
            $table->string('material_name');
            $table->string('material_type', 30)->default('fabric');
            $table->string('unit', 20)->default('M');
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_qty', 14, 4)->default(0);
            $table->decimal('reserved_qty', 14, 4)->default(0);
            $table->decimal('available_qty', 14, 4)->default(0);
            $table->decimal('min_stock_level', 14, 4)->default(0);
            $table->decimal('max_stock_level', 14, 4)->default(0);
            $table->decimal('reorder_point', 14, 4)->default(0);
            $table->string('location_bin', 100)->nullable();
            $table->string('batch_no', 100)->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['material_code', 'warehouse_id', 'batch_no'], 'inv_unique');
            $table->index('material_code');
            $table->index('material_type');
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 30);    // IN | OUT | TRANSFER | ADJUSTMENT
            $table->string('reference_type', 50);      // PO_RECEIPT | MRP_ISSUE | RETURN | ADJUSTMENT | INITIAL
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('material_code', 100);
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 20)->default('M');
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('batch_no', 100)->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('material_code');
            $table->index('transaction_type');
            $table->index('reference_type');
            $table->index('created_at');
        });

        // === PROCUREMENT TABLES ===
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->integer('lead_time_days')->default(0);
            $table->string('status', 30)->default('active');  // active | inactive | blacklisted
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery')->nullable();
            $table->string('status', 30)->default('draft');  // draft | sent | confirmed | received | partial | cancelled
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('material_code', 100);
            $table->string('material_name');
            $table->string('unit', 20)->default('M');
            $table->decimal('quantity', 14, 4);
            $table->decimal('received_qty', 14, 4)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->date('expected_date')->nullable();
            $table->string('status', 30)->default('pending');  // pending | partial | received | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('material_code');
        });

        Schema::create('po_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();
            $table->foreignId('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->date('received_date');
            $table->string('reference_number')->nullable();  // Delivery note / packing list
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('po_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_item_id')->constrained('po_items')->cascadeOnDelete();
            $table->string('material_code', 100);
            $table->decimal('quantity_received', 14, 4);
            $table->string('batch_no', 100)->nullable();
            $table->string('warehouse_location')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_receipt_items');
        Schema::dropIfExists('po_receipts');
        Schema::dropIfExists('po_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouses');
    }
};
