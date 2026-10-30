<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subcon_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mtp_id')->unique();
            $table->string('code', 100)->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->string('contract_no', 191)->nullable();
            $table->date('contract_date')->nullable();
            $table->string('appendix_no', 191)->nullable();
            $table->date('appendix_date')->nullable();
            $table->string('tax_code', 50)->nullable();
            $table->string('customs_doc_no', 191)->nullable();
            $table->date('customs_doc_date')->nullable();
            $table->string('form_12', 191)->nullable();
            $table->date('form_12_approved_date')->nullable();
            $table->string('form_18a', 191)->nullable();
            $table->date('form_18a_received_date')->nullable();
            $table->date('out_date')->nullable();
            $table->string('truck_plate', 50)->nullable();
            $table->date('in_date')->nullable();
            $table->string('truck_plate_2', 50)->nullable();
            $table->string('invoice_no', 191)->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('actual_received_qty', 15, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('export_bank_account', 191)->nullable();
            $table->date('customs_declaration_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('subcon_contracts'); }
};
