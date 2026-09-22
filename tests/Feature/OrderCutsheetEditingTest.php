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

class OrderCutsheetEditingTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Schema::table('ocs', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('order_type')->default('cmt');
            $table->string('material_ownership')->default('factory');
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->date('expected_ship_date')->nullable();
            $table->string('priority')->default('medium');
            $table->text('order_notes')->nullable();
        });
        Schema::table('bom_headers', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->string('bom_kind')->default('template');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('mapping_status')->default('ready');
            $table->string('style_name')->nullable();
        });
        Schema::create('customer_info', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand')->nullable();
        });
        Schema::create('customer_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('size_name');
            $table->integer('sort_order')->default(0);
        });
        DB::table('customer_info')->insert(['id' => 1, 'name' => 'Sample Customer']);
        DB::table('customer_sizes')->insert(['customer_id' => 1, 'size_name' => 'M']);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_ADMIN]));
    }

    public function test_orders_remain_editable_after_status_changes_and_keep_their_bom(): void
    {
        $templateId = DB::table('bom_headers')->insertGetId(['style_no' => 'S-001']);
        $bomId = DB::table('bom_headers')->insertGetId([
            'style_no' => 'S-001', 'bom_kind' => 'order', 'template_id' => $templateId,
        ]);

        foreach (['pending', 'confirmed', 'in_production', 'completed', 'released', 'closed'] as $status) {
            $cs = 'CS-' . $status;
            $this->createOcsRecord(['CS' => $cs, 'status' => $status, 'customer_id' => 1, 'bom_header_id' => $bomId]);
            $id = DB::table('ocs')->where('CS', $cs)->value('id');
            DB::table('order_sizes')->insert(['cutsheet_id' => $id, 'size_name' => 'M', 'quantity' => 100]);

            $this->get(route('admin.ocs.index'))->assertOk()->assertSee(route('admin.ocs.edit', $id));
            $this->get(route('admin.ocs.edit', $id))->assertOk();
            $payload = [
                'CS' => $cs, 'CsDate' => '2026-04-20', 'SNo' => 'S-001', 'Sname' => 'Sample Style',
                'Customer' => 'Sample Customer', 'customer_id' => 1, 'Color' => 'Blue', 'ONum' => 'PO-001',
                'Qty' => 140, 'order_type' => 'cmt', 'material_ownership' => 'factory',
                'bom_header_id' => $templateId, 'sizes' => [['size_name' => 'M', 'quantity' => 140]],
            ];
            $this->put(route('admin.ocs.update', $id), $payload)
                ->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertDatabaseHas('ocs', ['id' => $id, 'Qty' => 140, 'status' => $status, 'bom_header_id' => $bomId]);
            $this->assertDatabaseHas('order_sizes', ['cutsheet_id' => $id, 'size_name' => 'M', 'quantity' => 140]);

            $payload['Qty'] = 150;
            $this->put(route('admin.ocs.update', $id), $payload)->assertSessionHasErrors('sizes');
            $this->assertDatabaseHas('ocs', ['id' => $id, 'Qty' => 140]);

            if ($status !== 'pending') {
                $this->delete(route('admin.ocs.destroy', $id))->assertSessionHas('error');
                $this->assertDatabaseHas('ocs', ['id' => $id]);
            }
        }
        $this->assertDatabaseCount('bom_headers', 2);
    }

    public function test_norm_links_order_image_and_falls_back_to_template_bom_image(): void
    {
        $templateId = DB::table('bom_headers')->insertGetId([
            'style_no' => 'BOM-IMAGE', 'image_path' => 'bom-images/template.png',
        ]);
        $bomId = DB::table('bom_headers')->insertGetId([
            'style_no' => 'BOM-IMAGE', 'version' => 'V1-CS', 'bom_kind' => 'order', 'template_id' => $templateId,
        ]);
        $this->createOcsRecord(['bom_header_id' => $bomId, 'image_path' => 'ocs-images/order.png']);
        $id = DB::table('ocs')->value('id');
        $ocsUrl = route('admin.ocs.image', $id, false);
        $templateUrl = route('admin.bom.image', $templateId, false);
        $bomUrl = route('admin.bom.image', $bomId, false);
        $this->get(route('admin.norm.materials'))->assertOk()
            ->assertSee('data-image-url="' . $ocsUrl . '"', false)
            ->assertSee('data-image-url="' . $templateUrl . '"', false)
            ->assertDontSee('data-image-url="' . $bomUrl . '"', false);

        DB::table('bom_headers')->where('id', $bomId)->update(['image_path' => 'bom-images/order-bom.png']);
        $this->get(route('admin.norm.materials'))->assertOk()
            ->assertSee('data-image-url="' . $bomUrl . '"', false)
            ->assertDontSee('data-image-url="' . $templateUrl . '"', false);

        // Check the detail view also exposes both images, even with no material rows.
        $order = DB::table('ocs')->first();
        $order->bom_image_id = $bomId;
        $order->bom_style = 'BOM-IMAGE';
        $order->bom_version = 'V1-CS';
        $this->view('admin.norm.materials', [
            'order' => $order, 'rows' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50),
        ])->assertSee($ocsUrl)->assertSee($bomUrl);

        DB::table('bom_headers')->update(['image_path' => null]);
        DB::table('ocs')->update(['image_path' => null]);
        $this->get(route('admin.norm.materials'))->assertOk()->assertDontSee('data-image-url=', false);
    }

    private function imagePayload(): array
    {
        return [
            'CS' => 'CS-IMAGE', 'CsDate' => '2026-09-22', 'SNo' => 'S-001', 'Sname' => 'Sample Style',
            'Customer' => 'Sample Customer', 'customer_id' => 1, 'Color' => 'Blue', 'ONum' => 'PO-001',
            'Qty' => 100, 'order_type' => 'cmt', 'material_ownership' => 'factory',
            'sizes' => [['size_name' => 'M', 'quantity' => 100]],
        ];
    }

    private function uploadedImage(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sample.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
    }

    public function test_image_upload_display_preservation_replacement_and_deletion(): void
    {
        Storage::fake('local');
        $this->get(route('admin.ocs.create'))->assertOk()->assertSee('multipart/form-data')->assertSee('name="image"', false);
        $this->post(route('admin.ocs.store'), $this->imagePayload() + ['image' => $this->uploadedImage()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $order = DB::table('ocs')->where('CS', 'CS-IMAGE')->first();
        $this->assertStringStartsWith('ocs-images/', $order->image_path);
        Storage::disk('local')->assertExists($order->image_path);
        $this->get(route('admin.ocs.image', $order->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.ocs.edit', $order->id))->assertOk()
            ->assertSee(route('admin.ocs.image', $order->id, false))->assertSee('multipart/form-data');

        DB::table('ocs')->where('id', $order->id)->update(['status' => 'confirmed']);
        $this->put(route('admin.ocs.update', $order->id), $this->imagePayload())->assertSessionHas('success');
        $this->assertDatabaseHas('ocs', ['id' => $order->id, 'status' => 'confirmed', 'image_path' => $order->image_path]);
        Storage::disk('local')->assertExists($order->image_path);

        $this->put(route('admin.ocs.update', $order->id), $this->imagePayload() + ['image' => $this->uploadedImage()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $replacement = DB::table('ocs')->where('id', $order->id)->value('image_path');
        $this->assertNotSame($order->image_path, $replacement);
        Storage::disk('local')->assertMissing($order->image_path);
        Storage::disk('local')->assertExists($replacement);
        $this->get(route('admin.ocs.image', $order->id))->assertOk();

        Schema::create('order_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutsheet_id');
        });
        DB::table('ocs')->where('id', $order->id)->update(['status' => 'pending']);
        $this->delete(route('admin.ocs.destroy', $order->id))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($replacement);
        $this->get(route('admin.ocs.image', $order->id))->assertNotFound();
    }

    public function test_invalid_images_are_rejected_and_failed_save_removes_new_upload(): void
    {
        Storage::fake('local');
        foreach ([UploadedFile::fake()->create('fake.png', 1, 'text/plain'), $this->uploadedImage()->size(2049)] as $file) {
            $this->post(route('admin.ocs.store'), $this->imagePayload() + ['image' => $file])->assertSessionHasErrors('image');
        }
        $this->assertDatabaseCount('ocs', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());

        // Force a database failure after the image is stored to verify cleanup.
        Schema::drop('order_sizes');
        $this->post(route('admin.ocs.store'), $this->imagePayload() + ['image' => $this->uploadedImage()])->assertSessionHas('error');
        $this->assertDatabaseCount('ocs', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }
}
