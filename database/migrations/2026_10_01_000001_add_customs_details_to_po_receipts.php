<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('po_receipts', 'customs_declaration_date')) {
                $table->date('customs_declaration_date')->nullable();
            }
            if (!Schema::hasColumn('po_receipts', 'customs_declaration_number')) {
                $table->string('customs_declaration_number', 100)->nullable();
            }
            if (!Schema::hasColumn('po_receipts', 'contract_number')) {
                $table->string('contract_number', 100)->nullable();
            }
        });

        Schema::table('po_receipt_items', function (Blueprint $table) {
            if (!Schema::hasColumn('po_receipt_items', 'customs_material_code')) {
                $table->string('customs_material_code', 100)->nullable();
            }
            if (!Schema::hasColumn('po_receipt_items', 'customs_unit_price')) {
                $table->decimal('customs_unit_price', 14, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('po_receipts', function (Blueprint $table) {
            foreach (['customs_declaration_date', 'customs_declaration_number', 'contract_number'] as $column) {
                if (Schema::hasColumn('po_receipts', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('po_receipt_items', function (Blueprint $table) {
            foreach (['customs_material_code', 'customs_unit_price'] as $column) {
                if (Schema::hasColumn('po_receipt_items', $column)) $table->dropColumn($column);
            }
        });
    }
};
