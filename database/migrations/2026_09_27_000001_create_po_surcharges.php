<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('po_surcharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('description', 191);
            $table->decimal('quantity', 14, 4)->default(0);
            $table->string('unit', 20)->default('EA');
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('total_price', 18, 4)->default(0);
            $table->timestamps();
            $table->index('po_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_surcharges');
    }
};
