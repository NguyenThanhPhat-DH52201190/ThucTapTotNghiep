<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class PurchaseOrderPdfTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            foreach (['name', 'address', 'contact_person', 'phone', 'email'] as $key) $table->string($key)->nullable();
        });
        Schema::create('materials', function (Blueprint $table) { $table->id(); $table->string('color')->nullable(); $table->string('size')->nullable(); });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('supplier_id'); $table->string('po_number'); $table->date('order_date');
            $table->string('currency'); $table->decimal('total_amount', 14, 4); $table->text('notes')->nullable(); $table->string('status'); $table->timestamps();
        });
        (require database_path('migrations/2026_09_22_000007_add_pdf_settings_to_purchase_orders.php'))->up();
        Schema::create('po_items', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('po_id'); $table->unsignedBigInteger('material_id')->nullable();
            foreach (['material_code', 'material_name', 'color', 'unit', 'notes'] as $key) $table->text($key)->nullable();
            foreach (['quantity', 'unit_price', 'total_price'] as $key) $table->decimal($key, 14, 4);
        });
        DB::table('suppliers')->insert(['id' => 1, 'name' => 'Công ty Vải Việt', 'address' => "12 Nguyễn Huệ\nViệt Nam", 'contact_person' => 'Nguyễn An', 'email' => 'supplier@example.test']);
        DB::table('materials')->insert(['id' => 1, 'color' => 'YELLOW FL6064', 'size' => '148 cm']);
        DB::table('purchase_orders')->insert(['id' => 1, 'supplier_id' => 1, 'po_number' => 'PO-4613', 'order_date' => '2026-09-19', 'currency' => 'USD', 'total_amount' => 633, 'status' => 'confirmed']);
        DB::table('po_items')->insert(['po_id' => 1, 'material_id' => 1, 'material_code' => 'FAB-001', 'material_name' => 'AQUA 470 OR FL', 'unit' => 'M', 'quantity' => 100, 'unit_price' => 6.33, 'total_price' => 633, 'notes' => "82% PVC 18% POLYESTER\nWeight: 470gsm"]);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_ADMIN]));
    }

    public function test_pdf_download_and_settings_preserve_purchase_order_data(): void
    {
        $settings = ['reference' => 'REF-123', 'shipping_mark' => 'PO 4613', 'consignee' => "Global Safewear Vietnam Co.Ltd\nHo Chi Minh City", 'payment_details' => 'TT', 'freight_terms' => 'Freight terms are FOB Auckland', 'shipment_date' => 'ASAP PLEASE CONFIRM', 'revised' => 1];
        $response = $this->post(route('admin.procurement.pdf', 1), $settings)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('po-4613.pdf', $response->headers->get('Content-Disposition'));
        $saved = json_decode(DB::table('purchase_orders')->value('pdf_settings'), true);
        $this->assertSame($settings['consignee'], $saved['consignee']);
        $this->assertTrue($saved['revised']);
        $this->assertDatabaseHas('purchase_orders', ['id' => 1, 'total_amount' => 633, 'status' => 'confirmed']);
        $this->assertDatabaseCount('po_items', 1);
        $item = DB::table('po_items')->first(); $item->master_color = 'YELLOW FL6064'; $item->master_size = '148 cm';
        $this->view('admin.procurement.pdf', ['po' => DB::table('purchase_orders')->first(), 'supplier' => DB::table('suppliers')->first(), 'items' => collect([$item]), 'settings' => $saved])
            ->assertSee('Công ty Vải Việt')->assertSee('633.00')->assertSee('YELLOW FL6064')->assertSee('REVISED')->assertSee('ASAP PLEASE CONFIRM');
    }

    public function test_multi_page_pdf_and_access_control(): void
    {
        $item = (array) DB::table('po_items')->first(); unset($item['id']);
        for ($i = 0; $i < 40; $i++) DB::table('po_items')->insert($item);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_PPIC]));
        $response = $this->post(route('admin.procurement.pdf', 1), ['consignee' => 'Delivery address'])->assertOk();
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        $this->post(route('admin.procurement.pdf', 1), ['reference' => str_repeat('x', 501)])->assertSessionHasErrors('reference');
        $this->post(route('admin.procurement.pdf', 999))->assertNotFound();
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_PROD]));
        $this->post(route('admin.procurement.pdf', 1))->assertForbidden();
    }
}
