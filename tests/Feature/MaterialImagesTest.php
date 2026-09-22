<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class MaterialImagesTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Storage::fake('local');
        foreach (['material_categories', 'material_subcategories'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id(); $table->string('name'); $table->string('image_path')->nullable(); $table->timestamps();
                if ($name === 'material_categories') $table->string('slug');
                else $table->unsignedBigInteger('category_id');
            });
        }
        Schema::create('materials', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->id(); $table->unsignedBigInteger('category_id'); $table->unsignedBigInteger('subcategory_id')->nullable();
            foreach (['internal_code', 'old_code', 'material_name', 'material_type', 'color', 'size', 'unit'] as $name) $table->string($name)->nullable();
            $table->timestamps();
        });
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('material_id');
            foreach (['material_code', 'material_name', 'material_type', 'colour', 'size', 'unit'] as $name) $table->string($name)->nullable();
            $table->timestamps();
        });
        Schema::create('material_vendors', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('material_id'); $table->unsignedBigInteger('vendor_id'); $table->boolean('is_default_vendor')->default(false);
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id(); $table->string('code'); $table->string('name'); $table->string('status');
        });
        DB::table('material_categories')->insert(['id' => 1, 'name' => 'Fabric', 'slug' => 'fabric']);
        DB::table('material_subcategories')->insert(['id' => 1, 'category_id' => 1, 'name' => 'Woven']);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_ADMIN]));
    }

    private function payload(): array
    {
        return ['internal_code' => 'FAB-01', 'material_name' => 'Fabric', 'unit' => 'M', 'category_id' => 1, 'subcategory_id' => 1];
    }

    private function imageFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sample.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
    }

    public function test_material_images_are_independent_preserved_and_replaced(): void
    {
        $this->post(route('admin.master-data.materials.store'), $this->payload() + ['image' => $this->imageFile()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $material = DB::table('materials')->first();
        $path = $material->image_path;
        $this->assertStringStartsWith('material-images/materials/', $path);
        Storage::disk('local')->assertExists($path);
        $url = route('admin.master-data.material-image', $material->id, false);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.master-data.materials'))->assertOk()->assertSee('name="image"', false)
            ->assertDontSee('name="category_image"', false)->assertDontSee('name="subcategory_image"', false)
            ->assertSee('data-image-url="' . $url . '"', false);
        $second = $this->payload(); $second['internal_code'] = 'FAB-02';
        $this->post(route('admin.master-data.materials.store'), $second)->assertSessionHas('success');
        $this->assertDatabaseHas('materials', ['internal_code' => 'FAB-02', 'image_path' => null]);
        $this->assertDatabaseHas('material_categories', ['id' => 1, 'image_path' => null]);
        $payload = $this->payload(); $payload['subcategory_id'] = null;
        $this->patch(route('admin.master-data.materials.update', $material->id), $payload)->assertSessionHas('success');
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'image_path' => $path, 'subcategory_id' => null]);
        $this->patch(route('admin.master-data.materials.update', $material->id), $payload + ['image' => $this->imageFile()])->assertSessionHas('success');
        $replacement = DB::table('materials')->where('id', $material->id)->value('image_path');
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertExists($replacement);
        $this->assertDatabaseHas('materials', ['internal_code' => 'FAB-02', 'image_path' => null]);
        Schema::drop('bom_items');
        $this->patch(route('admin.master-data.materials.update', $material->id), $payload + ['image' => $this->imageFile()])->assertServerError();
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'image_path' => $replacement]);
        Storage::disk('local')->assertExists($replacement);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_norm_links_the_material_image_from_code_and_name(): void
    {
        Schema::table('bom_headers', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable(); $table->string('image_path')->nullable();
        });
        Schema::create('order_material_requirements', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('cutsheet_id'); $table->unsignedBigInteger('bom_header_id'); $table->unsignedBigInteger('material_id')->nullable();
            foreach (['material_code', 'material_name', 'material_type', 'material_color', 'material_size', 'unit', 'stock_status'] as $field) $table->string($field)->default('');
            foreach (['product_qty', 'consumption_rate', 'waste_percent', 'required_qty', 'available_qty', 'shortage_qty'] as $field) $table->decimal($field)->default(0);
        });
        $this->post(route('admin.master-data.materials.store'), $this->payload() + ['image' => $this->imageFile()])->assertSessionHas('success');
        $materialId = DB::table('materials')->value('id');
        $bomId = DB::table('bom_headers')->insertGetId(['style_no' => 'BOM-01']);
        $this->createOcsRecord(['bom_header_id' => $bomId]);
        $id = DB::table('ocs')->value('id');
        DB::table('order_material_requirements')->insert([
            'cutsheet_id' => $id, 'bom_header_id' => $bomId, 'material_id' => $materialId,
            'material_code' => 'FAB-01', 'material_name' => 'Fabric', 'material_type' => 'fabric',
        ]);
        $this->mock(\App\Services\OrderMaterialRequirementService::class)->shouldReceive('sync')->with($id)->andReturn(collect());
        $url = route('admin.master-data.material-image', $materialId, false);
        $response = $this->get(route('admin.norm.materials.show', $id))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'data-image-url="' . $url . '"'));
        $response->assertSee('View image for FAB-01')->assertSee('View image for Fabric')->assertDontSee('data-category-image-url=', false);
        DB::table('material_categories')->update(['image_path' => 'old-category.png']);
        DB::table('materials')->update(['image_path' => null]);
        $this->get(route('admin.norm.materials.show', $id))->assertOk()->assertDontSee('data-image-url=', false)->assertSee('Fabric');
        $this->get($url)->assertNotFound();
        DB::table('materials')->delete();
        $this->get(route('admin.norm.materials.show', $id))->assertOk();
    }

    public function test_image_validation_and_material_without_subcategory(): void
    {
        foreach ([UploadedFile::fake()->create('fake.png', 1, 'text/plain'), $this->imageFile()->size(2049)] as $file) {
            $this->post(route('admin.master-data.materials.store'), $this->payload() + ['image' => $file])->assertSessionHasErrors('image');
        }
        $this->assertDatabaseCount('materials', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $payload = $this->payload(); unset($payload['subcategory_id']);
        $this->post(route('admin.master-data.materials.store'), $payload + ['image' => $this->imageFile()])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertNotNull(DB::table('materials')->value('image_path'));
    }

    public function test_mapping_filters_and_pagination_are_independent_of_material_pagination(): void
    {
        Schema::table('material_vendors', function (Blueprint $table) {
            $table->string('vendor_item_code')->nullable();
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->integer('lead_time_days')->default(0);
        });
        DB::table('suppliers')->insert(['id' => 1, 'code' => 'SUP-01', 'name' => 'Supplier', 'status' => 'active']);
        DB::table('material_categories')->insert(['id' => 2, 'name' => 'Trim', 'slug' => 'trim']);
        DB::table('material_subcategories')->insert(['id' => 2, 'category_id' => 2, 'name' => 'Buttons']);
        for ($i = 1; $i <= 25; $i++) {
            $materialId = DB::table('materials')->insertGetId([
                'internal_code' => sprintf('FAB-%03d', $i), 'material_name' => 'Fabric', 'unit' => 'M',
                'category_id' => 1, 'subcategory_id' => 1, 'material_type' => 'fabric',
            ]);
            DB::table('material_vendors')->insert(['material_id' => $materialId, 'vendor_id' => 1]);
        }
        $trimId = DB::table('materials')->insertGetId(['internal_code' => 'TRIM-01', 'material_name' => 'Button', 'unit' => 'PCS', 'category_id' => 2, 'subcategory_id' => 2, 'material_type' => 'trim']);
        DB::table('material_vendors')->insert(['material_id' => $trimId, 'vendor_id' => 1]);
        $response = $this->get(route('admin.master-data.materials', [
            'category_id' => 2, 'mapping_category_id' => 1, 'mapping_subcategory_id' => 1, 'mapping_page' => 2,
        ]))->assertOk();
        $mappings = $response->viewData('vendorMappings');
        $this->assertSame(25, $mappings->total());
        $this->assertCount(5, $mappings);
        $this->assertSame(2, $mappings->currentPage());
        $this->assertSame('FAB-021', $mappings->first()->internal_code);
        $this->assertSame(1, $response->viewData('materials')->currentPage());
        $this->assertSame('TRIM-01', $response->viewData('materials')->first()->internal_code);
        $this->assertStringContainsString('mapping_subcategory_id=1', $mappings->url(1));
        $this->assertStringContainsString('#materialMappings', $mappings->url(1));
        $this->get(route('admin.master-data.materials', ['mapping_subcategory_id' => 2]))->assertOk()
            ->assertViewHas('vendorMappings', fn ($rows) => $rows->total() === 1 && $rows->first()->internal_code === 'TRIM-01');
        $this->get(route('admin.master-data.materials', ['mapping_category_id' => 1, 'mapping_subcategory_id' => 2]))->assertOk()
            ->assertViewHas('vendorMappings', fn ($rows) => $rows->total() === 0);
    }
}
