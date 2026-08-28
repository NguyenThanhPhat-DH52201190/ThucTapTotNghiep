<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocs', function (Blueprint $table) {
            if (!Schema::hasColumn('ocs', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()
                    ->constrained('customer_info')->nullOnDelete();
            }

            if (!Schema::hasColumn('ocs', 'style_id')) {
                $table->foreignId('style_id')->nullable()
                    ->constrained('styles')->nullOnDelete();
            }
        });

        Schema::create('order_sizes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->integer('cutsheet_id');
            $table->foreign('cutsheet_id')->references('id')->on('ocs')->cascadeOnDelete();
            $table->string('size_name', 50);
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['cutsheet_id', 'size_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sizes');

        Schema::table('ocs', function (Blueprint $table) {
            if (Schema::hasColumn('ocs', 'style_id')) {
                $table->dropConstrainedForeignId('style_id');
            }

            if (Schema::hasColumn('ocs', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
