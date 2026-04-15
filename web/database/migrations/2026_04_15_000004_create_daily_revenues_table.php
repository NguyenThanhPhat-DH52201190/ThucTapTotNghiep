<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_revenues')) {
            Schema::create('daily_revenues', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('revenue_id');
                $table->date('work_date');
                $table->unsignedInteger('qty')->default(0);
                $table->timestamps();

                $table->unique(['revenue_id', 'work_date']);
                $table->index('work_date');
                $table->foreign('revenue_id')->references('id')->on('revenue')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_revenues')) {
            Schema::dropIfExists('daily_revenues');
        }
    }
};
