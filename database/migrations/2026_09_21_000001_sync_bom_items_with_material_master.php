<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bom_items')->whereNotNull('material_id')->orderBy('id')->chunkById(200, function ($items) {
            $materials = DB::table('materials')->whereIn('id', $items->pluck('material_id')->unique())->get()->keyBy('id');
            foreach ($items as $item) {
                $material = $materials->get($item->material_id);
                if (!$material) continue;
                DB::table('bom_items')->where('id', $item->id)->update([
                    'material_code' => $material->internal_code, 'material_name' => $material->material_name,
                    'material_type' => $material->material_type, 'colour' => $material->color,
                    'size' => $material->size, 'unit' => $material->unit, 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Master data synchronization is intentionally not reversible.
    }
};
