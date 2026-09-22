<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('norm_confirmations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutsheet_id');
            $table->unsignedBigInteger('bom_item_id');
            $table->decimal('yield_confirmed', 18, 4)->nullable();
            $table->decimal('waste_confirmed', 8, 2)->nullable();
            $table->decimal('bom_yield_at_confirmation', 18, 4);
            $table->decimal('bom_waste_at_confirmation', 8, 2);
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['cutsheet_id', 'bom_item_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('norm_confirmations'); }
};
