<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class BomImageTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Storage::fake('local');
        $this->mock(AuditTrailService::class)->shouldReceive('record')->byDefault();
        Schema::table('bom_headers', function (Blueprint $table) {
            foreach (['image_path', 'style_name', 'customer', 'notes'] as $name) $table->string($name)->nullable();
            foreach (['style_id', 'customer_id', 'created_by'] as $name) $table->unsignedBigInteger($name)->nullable();
            $table->date('effective_date')->nullable();
            $table->decimal('total_cmt')->default(0);
            $table->string('bom_kind')->default('template');
        });
        Schema::create('styles', function (Blueprint $table) {
            $table->id(); $table->string('style_no'); $table->string('style_name'); $table->timestamps();
        });
        Schema::create('customer_info', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('brand')->nullable();
        });
        Schema::create('customer_sizes', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->string('size_name'); $table->integer('sort_order');
        });
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug');
        });
        Schema::create('materials', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('category_id');
            foreach (['internal_code', 'material_name', 'material_type', 'color', 'size', 'unit'] as $name) $table->string($name)->nullable();
        });
        Schema::create('material_vendors', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('material_id'); $table->boolean('is_default_vendor'); $table->decimal('unit_price');
        });
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('bom_header_id'); $table->unsignedBigInteger('material_id');
            foreach (['material_code', 'material_name', 'material_type', 'colour', 'size', 'size_rule', 'unit', 'source', 'remark'] as $name) $table->string($name)->nullable();
            foreach (['width', 'consumption_rate', 'waste_percent', 'unit_cost', 'total_cost'] as $name) $table->decimal($name, 14, 4)->nullable();
            $table->integer('sort_order'); $table->timestamps();
        });
        Schema::create('bom_item_customer_sizes', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('bom_item_id'); $table->unsignedBigInteger('customer_size_id'); $table->timestamps();
        });
        DB::table('material_categories')->insert(['id' => 1, 'name' => 'Fabric', 'slug' => 'fabric']);
        DB::table('materials')->insert(['category_id' => 1, 'internal_code' => 'FAB-01', 'material_name' => 'Fabric', 'material_type' => 'fabric', 'unit' => 'M']);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_ADMIN]));
    }

    private function payload(): array
    {
        return ['style_no' => 'STYLE-IMAGE', 'style_name' => 'Jacket', 'change_reason' => 'Customer revision',
            'items' => [['material_code' => 'FAB-01', 'category_id' => 1, 'consumption_rate' => 1]]];
    }

    private function imageFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sample.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
    }

    public function test_bom_image_upload_display_preserve_replace_and_delete(): void
    {
        $this->get(route('admin.bom.create'))->assertOk()->assertSee('multipart/form-data')->assertSee('name="image"', false);
        $this->post(route('admin.bom.store'), $this->payload() + ['image' => $this->imageFile()])->assertSessionHasNoErrors()->assertSessionHas('success');
        $bom = DB::table('bom_headers')->first();
        $this->assertStringStartsWith('bom-images/', $bom->image_path);
        Storage::disk('local')->assertExists($bom->image_path);
        $this->get(route('admin.bom.image', $bom->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.bom.index'))->assertOk()->assertSee('data-image-url="' . route('admin.bom.image', $bom->id, false) . '"', false);
        $this->get(route('admin.bom.edit', $bom->id))->assertOk()->assertSee(route('admin.bom.image', $bom->id, false));
        $this->put(route('admin.bom.update', $bom->id), $this->payload())->assertSessionHas('success');
        $this->assertDatabaseHas('bom_headers', ['id' => $bom->id, 'image_path' => $bom->image_path]);
        $this->put(route('admin.bom.update', $bom->id), $this->payload() + ['image' => $this->imageFile()])->assertSessionHas('success');
        $newPath = DB::table('bom_headers')->where('id', $bom->id)->value('image_path');
        $this->assertNotSame($bom->image_path, $newPath);
        Storage::disk('local')->assertMissing($bom->image_path);
        Storage::disk('local')->assertExists($newPath);
        $this->delete(route('admin.bom.destroy', $bom->id))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($newPath);
        $this->get(route('admin.bom.image', $bom->id))->assertNotFound();
    }

    public function test_image_validation_and_transaction_failure_cleanup(): void
    {
        foreach ([UploadedFile::fake()->create('fake.png', 1, 'text/plain'), $this->imageFile()->size(2049)] as $image) {
            $this->post(route('admin.bom.store'), $this->payload() + ['image' => $image])->assertSessionHasErrors('image');
        }
        Schema::drop('styles');
        $this->post(route('admin.bom.store'), $this->payload() + ['image' => $this->imageFile()])->assertSessionHas('error');
        $this->assertDatabaseCount('bom_headers', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }
}
