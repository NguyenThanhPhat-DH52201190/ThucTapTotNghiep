<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MRP Headers - Đợt tính toán MRP
        Schema::create('mrp_headers', function (Blueprint $table) {
            $table->id();
            $table->string('mrp_code', 50);                          // MRP-20260727-001
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status', 30)->default('draft');           // draft | calculated | approved
            $table->integer('total_materials')->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique('mrp_code');
        });

        // MRP Items - Chi tiết nhu cầu từng nguyên vật liệu
        Schema::create('mrp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mrp_header_id')->constrained()->cascadeOnDelete();
            $table->string('material_code');                          // Mã vật tư (từ BOM)
            $table->string('material_name');                          // Tên vật tư
            $table->string('material_type', 30)->default('fabric');   // fabric | lining | trim | thread | zipper | ...
            $table->string('unit', 20)->default('M');

            // Nhu cầu
            $table->decimal('gross_requirement', 14, 4)->default(0);  // Tổng nhu cầu
            $table->decimal('on_hand_stock', 14, 4)->default(0);      // Tồn kho hiện tại
            $table->decimal('on_order_stock', 14, 4)->default(0);     // Đã đặt mua
            $table->decimal('allocated_stock', 14, 4)->default(0);    // Đã cấp phát
            $table->decimal('net_requirement', 14, 4)->default(0);    // Nhu cầu thực = Gross - (OnHand + OnOrder - Allocated)

            // An toàn
            $table->decimal('safety_stock', 14, 4)->default(0);
            $table->decimal('planned_order_qty', 14, 4)->default(0);  // Số lượng đề xuất đặt mua

            // Thời gian
            $table->integer('lead_time_days')->default(0);
            $table->date('order_release_date')->nullable();           // Ngày đề xuất đặt hàng
            $table->date('order_receipt_date')->nullable();           // Ngày dự kiến nhận hàng

            $table->string('status', 30)->default('pending');         // pending | ordered | received | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('material_code');
            $table->index('status');
        });

        // MRP Source Details - Chi tiết nguồn nhu cầu (từ MTP nào, BOM nào)
        Schema::create('mrp_source_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mrp_item_id')->constrained('mrp_items')->cascadeOnDelete();
            $table->unsignedBigInteger('mtp_id')->nullable();         // Từ đơn hàng MTP nào
            $table->unsignedBigInteger('bom_item_id')->nullable();    // Từ BOM item nào
            $table->string('cu', 255)->nullable();                    // Mã đơn hàng
            $table->decimal('required_qty', 14, 4)->default(0);       // Số lượng yêu cầu
            $table->decimal('consumption_rate', 12, 4)->default(0);   // Định mức
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrp_source_details');
        Schema::dropIfExists('mrp_items');
        Schema::dropIfExists('mrp_headers');
    }
};
