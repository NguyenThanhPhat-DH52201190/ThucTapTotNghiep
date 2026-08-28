<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BOM Headers - Đầu phiếu định mức
        Schema::create('bom_headers', function (Blueprint $table) {
            $table->id();
            $table->string('style_no');                    // SNo from OCS
            $table->string('style_name')->nullable();      // Sname from OCS
            $table->string('customer')->nullable();        // Customer from OCS
            $table->string('version')->default('V1');      // Version control
            $table->string('status')->default('draft');    // draft, active, archived
            $table->decimal('total_fabric_cost', 12, 2)->default(0);
            $table->decimal('total_trim_cost', 12, 2)->default(0);
            $table->decimal('total_labor_cost', 12, 2)->default(0);
            $table->decimal('total_cmt', 12, 2)->default(0);
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('style_no');
        });

        // BOM Items - Chi tiết định mức nguyên vật liệu
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_header_id')->constrained()->cascadeOnDelete();
            $table->string('material_code');                           // Code từ file Excel (EX-V-TPU-180306-GRY)
            $table->string('material_name');                           // Description
            $table->string('material_type')->default('fabric');        // fabric, lining, pocket, trim, thread, zipper, label, elastic, other
            $table->string('colour')->nullable();                      // Colour
            $table->string('size')->nullable();                        // Size
            $table->decimal('width', 10, 2)->nullable();               // Width (khổ vải)
            $table->string('unit', 20)->default('M');                  // Unit: M, YD, PCS, KG, SET...
            $table->decimal('consumption_rate', 12, 4)->default(0);    // Yield - Định mức tiêu hao
            $table->decimal('waste_percent', 5, 2)->default(0);        // % hao hụt
            $table->decimal('unit_cost', 12, 2)->default(0);           // Đơn giá
            $table->decimal('total_cost', 12, 2)->default(0);          // Thành tiền
            $table->string('source')->default('local');                // local, imported
            $table->text('remark')->nullable();                        // Remark từ file Excel
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('material_code');
            $table->index('material_type');
        });

        // Tech Packs - Thông số kỹ thuật
        Schema::create('tech_packs', function (Blueprint $table) {
            $table->id();
            $table->string('style_no');
            $table->string('style_name')->nullable();
            $table->string('customer')->nullable();
            $table->foreignId('bom_header_id')->nullable()->constrained()->nullOnDelete();
            $table->json('size_spec')->nullable();         // Thông số size (S, M, L, XL...)
            $table->json('color_way')->nullable();         // Phối màu
            $table->text('sewing_instructions')->nullable();     // Hướng dẫn may
            $table->text('cutting_instructions')->nullable();    // Hướng dẫn cắt
            $table->text('finishing_instructions')->nullable();  // Hướng dẫn hoàn thiện
            $table->text('packing_instructions')->nullable();    // Hướng dẫn đóng gói
            $table->json('size_grading')->nullable();       // Giác sơ đồ (size grading)
            $table->string('sample_image')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('style_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_packs');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('bom_headers');
    }
};
