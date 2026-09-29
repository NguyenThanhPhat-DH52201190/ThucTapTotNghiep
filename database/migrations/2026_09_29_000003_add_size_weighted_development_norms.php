<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_norm_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_norm_id')->constrained('development_norms')->cascadeOnDelete();
            $table->string('size_name', 50);
            $table->decimal('quantity', 14, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['development_norm_id', 'size_name']);
        });

        Schema::create('development_norm_item_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_norm_item_id')->constrained('development_norm_items')->cascadeOnDelete();
            $table->foreignId('development_norm_size_id')->constrained('development_norm_sizes')->cascadeOnDelete();
            $table->decimal('yield_value', 18, 4)->default(0);
            $table->timestamps();
            $table->unique(['development_norm_item_id', 'development_norm_size_id'], 'dev_norm_item_size_unique');
        });

        foreach (DB::table('development_norms')->orderBy('id')->get() as $norm) {
            $orderSizes = DB::table('order_sizes')->where('cutsheet_id', $norm->cutsheet_id)->orderBy('id')->get();
            if ($orderSizes->isEmpty()) {
                $orderSizes = collect([(object) ['size_name' => 'ONE SIZE', 'quantity' => $norm->product_qty]]);
            }

            $sizeIds = [];
            foreach ($orderSizes as $index => $size) {
                $sizeIds[] = DB::table('development_norm_sizes')->insertGetId([
                    'development_norm_id' => $norm->id, 'size_name' => $size->size_name,
                    'quantity' => $size->quantity, 'sort_order' => $index,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            foreach (DB::table('development_norm_items')->where('development_norm_id', $norm->id)->get() as $item) {
                foreach ($sizeIds as $sizeId) {
                    DB::table('development_norm_item_sizes')->insert([
                        'development_norm_item_id' => $item->id, 'development_norm_size_id' => $sizeId,
                        'yield_value' => $item->yield_value, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('development_norm_item_sizes');
        Schema::dropIfExists('development_norm_sizes');
    }
};
