<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_norms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('cutsheet_id')->unique();
            $table->unsignedBigInteger('bom_header_id');
            $table->string('cs');
            $table->string('style_no')->nullable();
            $table->string('style_name')->nullable();
            $table->string('customer')->nullable();
            $table->decimal('product_qty', 18, 4)->default(0);
            $table->timestamp('copied_at');
            $table->timestamps();
            $table->index('cs');
        });

        Schema::create('development_norm_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('development_norm_id')->constrained('development_norms')->cascadeOnDelete();
            $table->unsignedBigInteger('bom_item_id')->nullable();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('material_code');
            $table->string('material_old_code')->nullable();
            $table->string('material_name');
            $table->string('material_type', 50)->nullable();
            $table->string('colour')->nullable();
            $table->string('size')->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('source_yield', 18, 4)->default(0);
            $table->decimal('source_waste_percent', 8, 2)->default(0);
            $table->decimal('yield_value', 18, 4)->default(0);
            $table->decimal('waste_percent', 8, 2)->default(0);
            $table->text('remark')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['development_norm_id', 'sort_order'], 'dev_norm_items_order_index');
        });

        // Seed immutable copies for OCSs which were already confirmed before deployment.
        DB::table('ocs')->whereIn('status', ['confirmed', 'in_production', 'completed', 'released', 'closed'])
            ->whereNotNull('bom_header_id')->orderBy('id')->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $normId = DB::table('development_norms')->insertGetId([
                        'cutsheet_id' => $order->id,
                        'bom_header_id' => $order->bom_header_id,
                        'cs' => $order->CS,
                        'style_no' => $order->SNo,
                        'style_name' => $order->Sname,
                        'customer' => $order->Customer,
                        'product_qty' => $order->Qty,
                        'copied_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $items = DB::table('bom_items as item')
                        ->leftJoin('materials as material', 'material.id', '=', 'item.material_id')
                        ->where('item.bom_header_id', $order->bom_header_id)
                        ->orderBy('item.sort_order')->orderBy('item.id')
                        ->get(['item.id', 'item.material_id', 'item.material_code', 'material.old_code as material_old_code',
                            'item.material_name', 'item.material_type', 'item.colour', 'item.size', 'item.width', 'item.unit',
                            'item.consumption_rate', 'item.waste_percent', 'item.remark', 'item.sort_order']);

                    foreach ($items as $item) {
                        DB::table('development_norm_items')->insert([
                            'development_norm_id' => $normId,
                            'bom_item_id' => $item->id,
                            'material_id' => $item->material_id,
                            'material_code' => $item->material_code,
                            'material_old_code' => $item->material_old_code,
                            'material_name' => $item->material_name,
                            'material_type' => $item->material_type,
                            'colour' => $item->colour,
                            'size' => $item->size,
                            'width' => $item->width,
                            'unit' => $item->unit,
                            'source_yield' => $item->consumption_rate,
                            'source_waste_percent' => $item->waste_percent,
                            'yield_value' => $item->consumption_rate,
                            'waste_percent' => $item->waste_percent,
                            'remark' => $item->remark,
                            'sort_order' => $item->sort_order,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_norm_items');
        Schema::dropIfExists('development_norms');
    }
};
