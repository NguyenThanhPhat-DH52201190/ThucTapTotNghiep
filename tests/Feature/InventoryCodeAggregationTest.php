<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class InventoryCodeAggregationTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Schema::create('materials', function (Blueprint $table): void {
            $table->id(); $table->string('internal_code'); $table->string('old_code')->nullable();
            $table->string('material_name'); $table->string('material_type')->nullable(); $table->string('unit')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('material_id'); $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable(); $table->string('custom_code')->nullable(); $table->string('location')->nullable();
            $table->string('lot_roll_no')->nullable(); $table->decimal('balance_qty', 14, 4)->default(0);
            $table->decimal('reserved_qty', 14, 4)->default(0); $table->decimal('min_stock_level', 14, 4)->default(0);
            $table->decimal('reorder_point', 14, 4)->default(0); $table->decimal('unit_cost', 14, 4)->default(0); $table->timestamps();
        });
        Schema::create('warehouses', function (Blueprint $table): void { $table->id(); $table->string('name'); $table->string('code'); $table->boolean('is_active')->default(true); });
        Schema::create('locations', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('warehouse_id'); $table->string('location_code'); $table->boolean('is_active')->default(true); });
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_ADMIN]));
    }

    public function test_inventory_index_aggregates_duplicate_material_codes_and_details_keep_balance_rows(): void
    {
        DB::table('materials')->insert([
            ['id' => 1, 'internal_code' => 'FAB-01', 'old_code' => 'OLD-A', 'material_name' => 'Fabric A', 'material_type' => 'fabric', 'unit' => 'M'],
            ['id' => 2, 'internal_code' => 'FAB-01', 'old_code' => 'OLD-B', 'material_name' => 'Fabric B', 'material_type' => 'trim', 'unit' => 'M'],
        ]);
        DB::table('inventory_balances')->insert([
            ['id' => 1, 'material_id' => 1, 'balance_qty' => 100, 'reserved_qty' => 10, 'min_stock_level' => 5, 'reorder_point' => 20, 'unit_cost' => 2, 'location' => 'A-01'],
            ['id' => 2, 'material_id' => 2, 'balance_qty' => 50, 'reserved_qty' => 5, 'min_stock_level' => 7, 'reorder_point' => 8, 'unit_cost' => 4, 'location' => 'B-02'],
        ]);

        $response = $this->get(route('admin.inventory.index'))->assertOk();
        $items = $response->viewData('items');
        $this->assertSame(1, $items->total());
        $summary = $items->first();
        $this->assertSame('FAB-01', $summary->material_code);
        $this->assertEquals(150, $summary->current_qty);
        $this->assertEquals(15, $summary->reserved_qty);
        $this->assertEquals(135, $summary->available_qty);
        $this->assertEquals(12, $summary->min_stock_level);
        $this->assertEquals(28, $summary->reorder_point);
        $this->assertEquals(400, $summary->total_value);
        $this->assertEqualsWithDelta(400 / 150, $summary->unit_cost, 0.0001);
        $this->get(route('admin.inventory.index', ['material_code' => 'OLD-B']))->assertOk()
            ->assertViewHas('items', fn ($rows) => $rows->total() === 1 && (float) $rows->first()->current_qty === 150.0);
        $this->get(route('admin.inventory.index', ['material_type' => 'fabric']))->assertOk()
            ->assertViewHas('items', fn ($rows) => $rows->total() === 1 && (float) $rows->first()->current_qty === 150.0);

        $details = $this->get(route('admin.inventory.code-details', 'FAB-01'))->assertOk();
        $this->assertCount(2, $details->viewData('items'));
        $details->assertSee('OLD-A')->assertSee('OLD-B')->assertSee('Fabric')->assertSee('Trim')->assertSee('A-01')->assertSee('B-02');
    }
}
