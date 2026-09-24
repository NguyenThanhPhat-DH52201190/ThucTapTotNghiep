<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class NormMaterialDefectsTest extends TestCase
{
    use CreatesLegacySchema;
    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        (require database_path('migrations/2026_09_24_000001_create_norm_material_defects.php'))->up();
        Storage::fake('local');
        Schema::create('bom_items', function ($t) { $t->id(); $t->unsignedBigInteger('bom_header_id'); });
        Schema::create('order_material_requirements', function ($t) {
            $t->id();
            foreach (['cutsheet_id', 'bom_header_id', 'bom_item_id', 'material_id'] as $field) $t->unsignedBigInteger($field);
            foreach (['material_code', 'material_name', 'material_color', 'material_size', 'unit'] as $field) $t->string($field);
            $t->decimal('required_qty', 18, 4)->default(100);
        });
        $this->createOcsRecord(['bom_header_id' => 1]);
        $this->orderId = DB::table('ocs')->value('id');
        foreach ([1, 2] as $id) {
            DB::table('bom_items')->insert(['id' => $id, 'bom_header_id' => 1]);
            DB::table('order_material_requirements')->insert([
                'cutsheet_id' => $this->orderId, 'bom_header_id' => 1, 'bom_item_id' => $id, 'material_id' => $id,
                'material_code' => 'MAT-'.$id, 'material_name' => 'Material '.$id, 'material_color' => 'Blue', 'material_size' => 'M', 'unit' => 'M',
            ]);
        }
        $this->actingAs($this->createUserRecord(['role' => 'admin']));
    }

    private function payload(): array
    {
        return ['submission_key' => (string) Str::uuid(), 'occurred_on' => now()->toDateString(), 'items' => [
            ['bom_item_id' => 1, 'defect_qty' => '2.5000', 'replacement_qty' => '1.5', 'reason' => 'Damaged during cutting', 'disposition' => 'scrap'],
            ['bom_item_id' => 2, 'defect_qty' => '3', 'replacement_qty' => '0', 'reason' => 'Can reuse', 'disposition' => 'reuse'],
        ]];
    }

    public function test_multiple_lines_are_snapshotted_without_changing_requirements_and_retry_is_idempotent(): void
    {
        $before = DB::table('order_material_requirements')->get()->toJson();
        $payload = $this->payload();
        $url = route('admin.norm.defects.store', $this->orderId);
        $this->get(route('admin.norm.defects', $this->orderId))->assertOk()->assertSee('id="defectForm"', false)->assertSee('MAT-2');
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect(route('admin.norm.defects', $this->orderId));
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('norm_material_defects', 2);
        $this->assertDatabaseHas('norm_material_defects', ['material_code' => 'MAT-1', 'defect_qty' => 2.5, 'replacement_qty' => 1.5]);
        $this->assertSame($before, DB::table('order_material_requirements')->get()->toJson());
        DB::table('order_material_requirements')->delete();
        $this->get(route('admin.norm.defects', $this->orderId))->assertOk()->assertSee('MAT-1')->assertSee('Damaged during cutting');
    }

    public function test_rejects_foreign_material_and_rolls_back_all_rows_and_images(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['image'] = UploadedFile::fake()->createWithContent('evidence.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        DB::table('order_material_requirements')->where('bom_item_id', 2)->update(['cutsheet_id' => 999]);
        $this->post(route('admin.norm.defects.store', $this->orderId), $payload)->assertSessionHasErrors('items.1.bom_item_id');
        $this->assertDatabaseCount('norm_material_defects', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_rejects_invalid_quantities_and_unauthorized_user(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['replacement_qty'] = 10;
        $this->post(route('admin.norm.defects.store', $this->orderId), $payload)->assertSessionHasErrors('items.0.replacement_qty');
        $payload['items'][0]['defect_qty'] = -1;
        $this->post(route('admin.norm.defects.store', $this->orderId), $payload)->assertSessionHasErrors('items.0.defect_qty');
        $this->assertDatabaseCount('norm_material_defects', 0);
        $this->actingAs($this->createUserRecord(['role' => 'warehouse']));
        $this->get(route('admin.norm.defects', $this->orderId))->assertForbidden();
        $this->post(route('admin.norm.defects.store', $this->orderId), $this->payload())->assertForbidden();
    }

    public function test_evidence_is_private_and_scoped_to_cu(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['image'] = UploadedFile::fake()->createWithContent('evidence.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->post(route('admin.norm.defects.store', $this->orderId), $payload)->assertSessionHasNoErrors();
        $id = DB::table('norm_material_defects')->min('id');
        $this->get(route('admin.norm.defects.image', [$this->orderId, $id]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.norm.defects.image', [999, $id]))->assertNotFound();
    }
}
