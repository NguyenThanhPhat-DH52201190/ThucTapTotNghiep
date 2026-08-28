<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cost Analysis - Phân tích chi phí cho từng style/đơn hàng
        Schema::create('cost_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('style_no');                               // Style number
            $table->string('style_name')->nullable();
            $table->string('customer')->nullable();
            $table->unsignedBigInteger('bom_header_id')->nullable();   // BOM used
            $table->unsignedBigInteger('ocs_id')->nullable();          // OCS order

            // Standard costs (từ BOM)
            $table->decimal('standard_fabric_cost', 14, 2)->default(0);   // Chi phí vải chuẩn
            $table->decimal('standard_trim_cost', 14, 2)->default(0);     // Chi phí phụ liệu chuẩn
            $table->decimal('standard_labor_cost', 14, 2)->default(0);    // Chi phí nhân công chuẩn
            $table->decimal('standard_cmt_cost', 14, 2)->default(0);      // Chi phí CMT chuẩn
            $table->decimal('standard_overhead_cost', 14, 2)->default(0); // Chi phí sản xuất chung
            $table->decimal('standard_total_cost', 14, 2)->default(0);    // Tổng chi phí chuẩn

            // Actual costs (từ sản xuất thực tế)
            $table->decimal('actual_fabric_cost', 14, 2)->default(0);
            $table->decimal('actual_trim_cost', 14, 2)->default(0);
            $table->decimal('actual_labor_cost', 14, 2)->default(0);
            $table->decimal('actual_cmt_cost', 14, 2)->default(0);
            $table->decimal('actual_overhead_cost', 14, 2)->default(0);
            $table->decimal('actual_total_cost', 14, 2)->default(0);

            // Revenue & Profit
            $table->decimal('revenue_per_unit', 14, 2)->default(0);       // Giá bán/đơn vị
            $table->integer('total_qty')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('total_profit', 14, 2)->default(0);
            $table->decimal('profit_margin_percent', 6, 2)->default(0);   // Tỷ suất lợi nhuận %

            // Variance
            $table->decimal('cost_variance', 14, 2)->default(0);          // Chênh lệch (Actual - Standard)
            $table->decimal('variance_percent', 6, 2)->default(0);        // % chênh lệch

            $table->string('period', 7)->nullable();                      // Kỳ: YYYY-MM
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('style_no');
            $table->index('period');
            $table->unique(['style_no', 'period'], 'cost_analysis_unique');
        });

        // Expense Tracking - Chi phí phát sinh
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->string('category', 50);           // labor | overhead | utility | maintenance | other
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('expense_date');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('cost_analyses');
    }
};
