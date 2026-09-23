<?php

namespace Tests\Feature;

use App\Services\CustomerStyleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class CustomerStylesTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Storage::fake('local');
        foreach (['ocs', 'bom_headers'] as $table) Schema::table($table, function ($t) {
            $t->unsignedBigInteger('customer_id')->nullable(); $t->string('image_path')->nullable();
        });
        Schema::table('bom_headers', function ($t) { $t->string('style_name')->nullable(); $t->string('customer')->nullable(); });
        Schema::create('customer_info', function ($t) { $t->id(); $t->string('name'); $t->string('brand')->nullable(); });
        DB::table('customer_info')->insert([['id' => 1, 'name' => 'Alpha'], ['id' => 2, 'name' => 'Beta']]);
        $this->actingAs($this->createUserRecord(['role' => 'admin']));
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('style.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_customer_scoped_codes_and_shared_image_replacement(): void
    {
        foreach ([1, 2] as $customer) {
            $this->post(route('admin.customer-styles.store', $customer), ['style_no' => 'S-001', 'style_name' => 'Jacket', 'image' => $this->image()])->assertSessionHasNoErrors();
        }
        $style = DB::table('customer_styles')->where('customer_id', 1)->first();
        $other = DB::table('customer_styles')->where('customer_id', 2)->first();
        $this->assertNotSame($style->image_path, $other->image_path);
        $this->post(route('admin.customer-styles.store', 1), ['style_no' => 'S-001', 'style_name' => 'Duplicate'])->assertSessionHasErrors('style_no');
        $this->createOcsRecord(['customer_id' => 1, 'SNo' => 'S-001']);
        $this->createOcsRecord(['CS' => 'CS-002', 'customer_id' => 2, 'SNo' => 'S-001']);
        $bom = DB::table('bom_headers')->insertGetId(['customer_id' => 1, 'style_no' => 'S-001']);
        $order = DB::table('ocs')->where('customer_id', 1)->first();
        $resolver = app(CustomerStyleService::class);
        $this->assertSame($style->image_path, $resolver->imagePath('ocs', $order->id));
        $this->assertSame($style->image_path, $resolver->imagePath('bom_headers', $bom));
        $this->get(route('admin.ocs.image', $order->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.bom.image', $bom))->assertOk();
        $this->put(route('admin.customer-styles.update', [1, $style->id]), ['style_no' => 'S-001', 'style_name' => 'New name', 'image' => $this->image()])->assertSessionHasNoErrors();
        $new = DB::table('customer_styles')->find($style->id)->image_path;
        Storage::disk('local')->assertMissing($style->image_path);
        $this->assertSame($new, $resolver->imagePath('ocs', $order->id));
        $this->assertSame($new, $resolver->imagePath('bom_headers', $bom));
        $this->assertSame($other->image_path, $resolver->imagePath('ocs', DB::table('ocs')->where('customer_id', 2)->value('id')));
        $this->put(route('admin.customer-styles.update', [1, $style->id]), ['style_no' => 'RENAMED', 'style_name' => 'New name'])->assertSessionHasErrors('style_no');
    }

    public function test_unregistered_styles_and_wrong_customer_are_rejected(): void
    {
        DB::table('customer_styles')->insert(['customer_id' => 1, 'style_no' => 'ALPHA', 'style_name' => 'Alpha style']);
        $this->post(route('admin.ocs.store'), ['customer_id' => 2, 'SNo' => 'ALPHA'])->assertSessionHasErrors('SNo');
        $this->post(route('admin.bom.store'), ['customer_id' => 2, 'style_no' => 'ALPHA'])->assertSessionHasErrors('style_no');
        $this->post(route('admin.bom.clone', 1), ['customer_id' => 2, 'style_no' => 'ALPHA'])->assertSessionHasErrors('style_no');
        $this->assertDatabaseCount('ocs', 0);
        $this->assertDatabaseCount('bom_headers', 0);
    }

    public function test_image_validation_and_permissions(): void
    {
        $this->post(route('admin.customer-styles.store', 1), ['style_no' => 'S', 'style_name' => 'Style', 'image' => UploadedFile::fake()->create('fake.png', 1, 'text/plain')])->assertSessionHasErrors('image');
        $this->post(route('admin.customer-styles.store', 1), ['style_no' => 'S', 'style_name' => 'Style', 'image' => $this->image()->size(2049)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('customer_styles', 0);
        $this->actingAs($this->createUserRecord(['role' => 'ie']));
        $this->post(route('admin.customer-styles.store', 1), ['style_no' => 'S', 'style_name' => 'Style'])->assertForbidden();
        $this->get(route('admin.customer-styles.index', 1))->assertForbidden();
    }

    public function test_import_copies_legacy_image_once_without_overwriting_catalog(): void
    {
        Storage::disk('local')->put('ocs-images/old.png', 'legacy');
        $this->createOcsRecord(['Customer' => 'Alpha', 'SNo' => 'S-001', 'image_path' => 'ocs-images/old.png']);
        $this->artisan('customer-styles:import-existing')->assertExitCode(0);
        $style = DB::table('customer_styles')->first();
        $this->assertSame(1, (int) $style->customer_id);
        $this->assertNotSame('ocs-images/old.png', $style->image_path);
        Storage::disk('local')->assertExists($style->image_path);
        $this->assertDatabaseHas('ocs', ['customer_id' => 1]);
        $this->artisan('customer-styles:import-existing')->assertExitCode(0);
        $this->assertDatabaseCount('customer_styles', 1);
        $this->assertCount(2, Storage::disk('local')->allFiles());
        $this->get(route('admin.customer-styles.index', 1))->assertOk()->assertSee('S-001');
    }
}
