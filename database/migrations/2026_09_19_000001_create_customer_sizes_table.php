<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customer_info')->cascadeOnDelete();
            $table->string('size_name', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['customer_id', 'size_name']);
        });
        DB::table('bom_headers')->where('bom_kind', 'order')->update(['mapping_status' => 'ready']);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sizes');
    }
};
