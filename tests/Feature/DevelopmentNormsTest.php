<?php

namespace Tests\Feature;

use App\Jobs\CreateRequisitionForCutsheet;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class DevelopmentNormsTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Schema::table('ocs', function (Blueprint $table): void {
            $table->string('requisition_job_status')->nullable();
            $table->text('requisition_job_error')->nullable();
        });
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->string('old_code')->nullable();
        });
        Schema::create('bom_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bom_header_id');
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('material_code');
            $table->string('material_name');
            $table->string('material_type')->nullable();
            $table->string('colour')->nullable();
            $table->string('size')->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('consumption_rate', 12, 4)->default(0);
            $table->decimal('waste_percent', 5, 2)->default(0);
            $table->text('remark')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });
        Schema::create('audit_trails', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('before_data')->nullable();
            $table->text('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        (require database_path('migrations/2026_09_28_000001_create_development_norms_tables.php'))->up();
        (require database_path('migrations/2026_09_29_000003_add_size_weighted_development_norms.php'))->up();
    }

    public function test_confirming_ocs_copies_bom_and_development_edits_stay_separate(): void
    {
        Queue::fake();
        $admin = $this->createUserRecord(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);
        $bomId = DB::table('bom_headers')->insertGetId(['style_no' => 'STYLE-DEV', 'version' => 'V2', 'status' => 'active']);
        $materialId = DB::table('materials')->insertGetId(['old_code' => 'OLD-001']);
        $itemId = DB::table('bom_items')->insertGetId([
            'bom_header_id' => $bomId, 'material_id' => $materialId, 'material_code' => 'MAT-001',
            'material_name' => 'Sample fabric', 'material_type' => 'fabric', 'colour' => 'Navy',
            'size' => 'M', 'width' => 58, 'unit' => 'M', 'consumption_rate' => 1.2500,
            'waste_percent' => 2.50, 'remark' => 'Test row', 'sort_order' => 1,
        ]);
        $this->createOcsRecord(['CS' => 'CS-DEV-1', 'status' => 'pending', 'bom_header_id' => $bomId]);
        $cutsheetId = DB::table('ocs')->where('CS', 'CS-DEV-1')->value('id');
        DB::table('order_sizes')->insert([
            ['cutsheet_id' => $cutsheetId, 'size_name' => 'S', 'quantity' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['cutsheet_id' => $cutsheetId, 'size_name' => 'M', 'quantity' => 60, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->patch(route('admin.ocs.status', $cutsheetId), ['status' => 'confirmed'])
            ->assertSessionHas('success');
        Queue::assertPushed(CreateRequisitionForCutsheet::class);

        $norm = DB::table('development_norms')->where('cutsheet_id', $cutsheetId)->first();
        $this->assertNotNull($norm);
        $normItem = DB::table('development_norm_items')->where('development_norm_id', $norm->id)->first();
        $this->assertSame('MAT-001', $normItem->material_code);
        $this->assertSame('OLD-001', $normItem->material_old_code);
        $this->assertEquals(1.25, $normItem->source_yield);
        $this->get(route('admin.development-norms.index'))->assertOk()->assertSee('Development Norms');
        $this->get(route('admin.development-norms.show', $cutsheetId))->assertOk();

        $developmentUser = $this->createUserRecord(['role' => User::ROLE_DEVELOPMENT]);
        $this->actingAs($developmentUser);
        $this->get(route('admin.development-norms.show', $cutsheetId))->assertOk()->assertSee('Sample fabric')->assertSee('CU Qty 40')->assertSee('CU Qty 60');
        $sizeIds = DB::table('development_norm_sizes')->where('development_norm_id', $norm->id)->pluck('id', 'size_name');
        $this->put(route('admin.development-norms.update', $cutsheetId), [
            'items' => [$normItem->id => [
                'size_rates' => [$sizeIds['S'] => 1.0, $sizeIds['M'] => 2.0], 'waste_percent' => 4.25,
            ]],
        ])->assertRedirect(route('admin.development-norms.show', $cutsheetId));

        $this->assertDatabaseHas('development_norm_items', [
            'id' => $normItem->id, 'source_yield' => 1.25, 'yield_value' => 1.6, 'waste_percent' => 4.25,
        ]);
        $this->assertDatabaseHas('development_norm_item_sizes', ['development_norm_item_id' => $normItem->id, 'development_norm_size_id' => $sizeIds['S'], 'yield_value' => 1.0]);
        $this->assertDatabaseHas('development_norm_item_sizes', ['development_norm_item_id' => $normItem->id, 'development_norm_size_id' => $sizeIds['M'], 'yield_value' => 2.0]);
        $this->assertDatabaseHas('bom_items', ['id' => $itemId, 'consumption_rate' => 1.25, 'waste_percent' => 2.5]);
        $this->assertDatabaseHas('ocs', ['id' => $cutsheetId, 'status' => 'confirmed']);

        $this->actingAs($admin)->put(route('admin.development-norms.update', $cutsheetId), [
            'items' => [$normItem->id => [
                'size_rates' => [$sizeIds['S'] => 1.5, $sizeIds['M'] => 2.0], 'waste_percent' => 4.25,
            ]],
        ])->assertRedirect(route('admin.development-norms.show', $cutsheetId));
        $this->assertDatabaseHas('development_norm_items', ['id' => $normItem->id, 'yield_value' => 1.8]);
    }
}
