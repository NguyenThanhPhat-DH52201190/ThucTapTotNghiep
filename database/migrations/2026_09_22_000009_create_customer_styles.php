<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_styles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('style_no', 191);
            $table->string('style_name', 191);
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'style_no']);
        });
    }

    public function down(): void { Schema::dropIfExists('customer_styles'); }
};
