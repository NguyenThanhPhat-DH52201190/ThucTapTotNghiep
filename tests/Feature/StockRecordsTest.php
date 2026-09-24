<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InventoryLedgerService;
use App\Services\OrderMaterialRequirementService;
use App\Services\StockRecordService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class StockRecordsTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        Schema::create('materials', function (Blueprint $table): void { $table->id(); $table->string('internal_code')->unique(); $table->string('material_name'); $table->string('color')->nullable(); $table->string('size')->nullable(); $table->string('unit')->default('M'); $table->string('material_type')->default('fabric'); $table->timestamps(); });
        Schema::create('bom_items', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('bom_header_id'); $table->unsignedBigInteger('material_id')->nullable(); $table->string('material_code'); $table->string('material_name'); $table->string('material_type'); $table->string('colour')->nullable(); $table->string('size')->nullable(); $table->string('unit')->default('M'); $table->decimal('consumption_rate', 12, 4)->default(0); $table->decimal('waste_percent', 8, 2)->default(0); $table->decimal('total_cost', 14, 2)->default(0); $table->timestamps(); });
        Schema::create('bom_colorways', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('bom_item_id'); $table->string('garment_color'); $table->string('material_color'); $table->timestamps(); });
        Schema::create('material_requisitions', function (Blueprint $table): void { $table->id(); $table->string('requisition_code'); $table->unsignedBigInteger('cutsheet_id'); $table->string('status'); $table->timestamps(); });
        Schema::create('requisition_items', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('requisition_id'); $table->unsignedBigInteger('material_id'); $table->string('material_color')->nullable(); $table->string('material_size')->nullable(); $table->decimal('requested_qty', 14, 4); $table->decimal('issued_qty', 14, 4)->default(0); $table->timestamps(); });
        Schema::create('inventory_balances', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('material_id'); $table->unsignedBigInteger('warehouse_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->string('material_color')->nullable(); $table->string('material_size')->nullable(); $table->string('lot_roll_no')->nullable(); $table->string('location')->nullable(); $table->decimal('balance_qty', 14, 4)->default(0); $table->decimal('reserved_qty', 14, 4)->default(0); $table->decimal('unit_cost', 14, 2)->default(0); $table->timestamps(); });
        Schema::create('inventory_reservations', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('requisition_item_id'); $table->unsignedBigInteger('inventory_balance_id'); $table->decimal('reserved_qty', 14, 4); $table->decimal('consumed_qty', 14, 4)->default(0); $table->string('status')->default('active'); $table->timestamps(); });
        Schema::create('inventory_transactions', function (Blueprint $table): void { $table->id(); $table->string('transaction_type'); $table->string('reference_type'); $table->unsignedBigInteger('reference_id')->nullable(); $table->string('reference_doc')->nullable(); $table->date('transaction_date'); $table->unsignedBigInteger('material_id'); $table->string('material_code'); $table->string('material_color')->nullable(); $table->string('material_size')->nullable(); $table->string('lot_roll_no')->nullable(); $table->string('location')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->decimal('quantity', 14, 4); $table->string('unit'); $table->unsignedBigInteger('from_warehouse_id')->nullable(); $table->unsignedBigInteger('to_warehouse_id')->nullable(); $table->decimal('unit_cost', 14, 2); $table->decimal('total_cost', 14, 2); $table->text('notes')->nullable(); $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps(); });
        Schema::create('warehouses', function (Blueprint $table): void { $table->id(); $table->string('name'); $table->string('type')->nullable(); $table->timestamps(); });
        Schema::create('material_issues', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('requisition_id'); $table->timestamps(); });
        Schema::create('audit_trails', function (Blueprint $table): void { $table->id(); $table->string('event_type'); $table->string('entity_type'); $table->unsignedBigInteger('entity_id')->nullable(); $table->unsignedBigInteger('user_id')->nullable(); $table->text('before_data')->nullable(); $table->text('after_data')->nullable(); $table->string('reason')->nullable(); $table->timestamps(); });
        Schema::table('ocs', fn (Blueprint $table) => $table->date('expected_ship_date')->nullable());
        Schema::table('bom_headers', fn (Blueprint $table) => $table->string('bom_kind')->default('template'));
        Schema::table('bom_items', fn (Blueprint $table) => $table->integer('sort_order')->default(1));
        Schema::create('customer_sizes', function (Blueprint $table) { $table->id(); $table->string('size_name'); });
        Schema::create('bom_item_customer_sizes', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('bom_item_id'); $table->unsignedBigInteger('customer_size_id'); });
        (require database_path('migrations/2026_09_19_000003_create_order_material_requirements_table.php'))->up();
        (require database_path('migrations/2026_09_22_000005_create_stock_records.php'))->up();
        foreach (['inventory_balances', 'inventory_transactions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) { $table->string('lot_no')->nullable(); $table->string('roll_no')->nullable(); });
        }
        DB::table('materials')->insert(['id' => 1, 'internal_code' => 'FAB-01', 'material_name' => 'Fabric', 'unit' => 'M', 'material_type' => 'fabric']);
        DB::table('inventory_balances')->insert(['id' => 1, 'material_id' => 1, 'balance_qty' => 1000, 'reserved_qty' => 0, 'material_color' => 'Red', 'material_size' => 'M']);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_ADMIN]));
    }

    private function order(string $code, int $qty, string $status = 'confirmed', string $color = 'Red'): int
    {
        $bomId = DB::table('bom_headers')->insertGetId(['style_no' => 'BOM-' . $code, 'version' => 'V1']);
        DB::table('bom_items')->insert(['bom_header_id' => $bomId, 'material_id' => 1, 'material_code' => 'FAB-01', 'material_name' => 'Fabric', 'material_type' => 'fabric', 'colour' => $color, 'size' => 'M', 'consumption_rate' => 1]);
        $this->createOcsRecord(['CS' => $code, 'Qty' => $qty, 'status' => $status, 'bom_header_id' => $bomId]);
        return DB::table('ocs')->where('CS', $code)->value('id');
    }

    private function record(): object
    {
        app(StockRecordService::class)->importInventory();
        return DB::table('stock_records')->first();
    }

    public function test_norm_confirmed_rates_survive_sync_and_are_scoped_to_each_order(): void
    {
        $id = $this->order('CU-NORM', 170, 'pending');
        $item = DB::table('bom_items')->first();
        DB::table('bom_items')->where('id', $item->id)->update(['consumption_rate' => 2.59, 'waste_percent' => 2]);
        $service = app(OrderMaterialRequirementService::class);
        $this->assertEqualsWithDelta(449.106, $service->sync($id)->first()->required_qty, .0001);
        $row = ['bom_item_id' => $item->id, 'revision' => 0, 'yield_plan' => 2.59, 'waste_plan' => 2, 'yield_confirmed' => 2.5, 'waste_confirmed' => 1];
        $this->put(route('admin.norm.materials.confirmed', $id), ['rows' => [$row], 'reason' => 'Measured consumption'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(429.25, $service->sync($id)->first()->required_qty, .0001);
        $this->assertDatabaseHas('bom_items', ['id' => $item->id, 'consumption_rate' => 2.59, 'waste_percent' => 2]);
        $this->createOcsRecord(['CS' => 'CU-OTHER', 'Qty' => 170, 'bom_header_id' => $item->bom_header_id]);
        $other = DB::table('ocs')->where('CS', 'CU-OTHER')->value('id');
        $this->assertEqualsWithDelta(449.106, $service->sync($other)->first()->required_qty, .0001);
        DB::table('bom_items')->where('id', $item->id)->update(['consumption_rate' => 3]);
        $this->assertEqualsWithDelta(429.25, $service->sync($id)->first()->required_qty, .0001);
        $this->assertDatabaseHas('norm_confirmations', ['cutsheet_id' => $id, 'yield_confirmed' => 2.5, 'waste_confirmed' => 1]);
        $this->assertDatabaseHas('audit_trails', ['event_type' => 'norm_confirmed_updated', 'entity_id' => $id]);
        $this->assertSame(1000.0, (float) DB::table('inventory_balances')->value('balance_qty'));
        $this->plan();
        $this->assertEqualsWithDelta(429.25, DB::table('order_material_requirements')->where('cutsheet_id', $id)->value('required_qty'), .0001);
        $this->put(route('admin.norm.materials.confirmed', $id), ['rows' => [$row]])->assertSessionHasErrors('rows');
        $row['revision'] = 1; $row['yield_plan'] = 3; $row['yield_confirmed'] = null; $row['waste_confirmed'] = 0;
        $this->put(route('admin.norm.materials.confirmed', $id), ['rows' => [$row]])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(510, $service->sync($id)->first()->required_qty, .0001);
    }

    public function test_norm_rejects_foreign_rows_invalid_rates_and_unauthorized_updates(): void
    {
        $id = $this->order('CU-NORM', 100);
        $other = $this->order('CU-OTHER', 100);
        $item = DB::table('bom_items')->orderByDesc('id')->first();
        $row = ['bom_item_id' => $item->id, 'revision' => 0, 'yield_plan' => 1, 'waste_plan' => 0, 'yield_confirmed' => 2, 'waste_confirmed' => 5];
        $this->put(route('admin.norm.materials.confirmed', $id), ['rows' => [$row]])->assertSessionHasErrors('rows.0.bom_item_id');
        $row['yield_confirmed'] = -1;
        $this->put(route('admin.norm.materials.confirmed', $other), ['rows' => [$row]])->assertSessionHasErrors('rows.0.yield_confirmed');
        $this->assertDatabaseCount('norm_confirmations', 0);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_PROD]));
        $this->put(route('admin.norm.materials.confirmed', $id), ['rows' => [$row]])->assertForbidden();
    }

    private function plan(): \Illuminate\Support\Collection
    {
        return app(StockRecordService::class)->plan($this->record(), app(OrderMaterialRequirementService::class));
    }

    public function test_priority_changes_move_shortage_without_changing_inventory(): void
    {
        $first = $this->order('CU-FIRST', 400);
        $second = $this->order('CU-SECOND', 700);
        $this->order('CU-CLOSED', 800, 'completed');
        $record = $this->record();
        $plan = $this->plan();
        $this->assertCount(2, $plan);
        $this->assertEquals(0, $plan[0]->shortage);
        $this->assertEquals(100, $plan[1]->shortage);
        $this->assertEquals(-100, $plan[1]->projected);
        $this->get(route('admin.stock-records.index'))->assertOk()->assertSee('FAB-01');
        $this->get(route('admin.stock-records.show', $record->id))->assertOk()->assertSee('CU-FIRST');
        $payload = ['revision' => 0, 'priorities' => [['cutsheet_id' => $first, 'sort_order' => 20], ['cutsheet_id' => $second, 'sort_order' => 10]]];
        $this->put(route('admin.stock-records.priorities', $record->id), $payload)->assertSessionHas('success');
        $plan = $this->plan();
        $this->assertSame($second, $plan[0]->order->id);
        $this->assertEquals(0, $plan[0]->shortage);
        $this->assertEquals(100, $plan[1]->shortage);
        $this->assertDatabaseHas('inventory_balances', ['id' => 1, 'balance_qty' => 1000, 'reserved_qty' => 0]);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseHas('audit_trails', ['event_type' => 'stock_priorities_changed']);
        $this->put(route('admin.stock-records.priorities', $record->id), $payload)->assertSessionHasErrors('priorities');
        $this->patch(route('admin.stock-records.position', $record->id), ['sort_order' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('stock_records', ['id' => $record->id, 'sort_order' => 1]);
    }

    public function test_issued_stock_is_not_deducted_twice_and_reservations_are_protected(): void
    {
        $first = $this->order('CU-FIRST', 400);
        $second = $this->order('CU-SECOND', 700);
        $req = DB::table('material_requisitions')->insertGetId(['requisition_code' => 'REQ', 'cutsheet_id' => $second, 'status' => 'partial']);
        $item = DB::table('requisition_items')->insertGetId(['requisition_id' => $req, 'material_id' => 1, 'material_color' => 'Red', 'material_size' => 'M', 'requested_qty' => 700, 'issued_qty' => 200]);
        DB::table('inventory_balances')->where('id', 1)->update(['balance_qty' => 800, 'reserved_qty' => 500]);
        DB::table('inventory_reservations')->insert(['requisition_item_id' => $item, 'inventory_balance_id' => 1, 'reserved_qty' => 700, 'consumed_qty' => 200, 'status' => 'active']);
        $plan = $this->plan();
        $this->assertEquals(100, $plan[0]->shortage);
        $this->assertEquals(200, $plan[1]->issued);
        $this->assertEquals(500, $plan[1]->remaining);
        $this->assertEquals(500, $plan[1]->reserved);
        $this->assertEquals(0, $plan[1]->shortage);
        $this->assertEquals(-100, $plan[1]->projected);
        $this->assertDatabaseHas('inventory_balances', ['id' => 1, 'balance_qty' => 800, 'reserved_qty' => 500]);
    }

    public function test_opening_snapshot_is_idempotent_and_history_reconciles_with_ledger(): void
    {
        $record = $this->record();
        app(InventoryLedgerService::class)->adjust(['balance_id' => 1, 'new_qty' => 900, 'reason' => 'Count correction']);
        $this->post(route('admin.stock-records.sync'))->assertSessionHas('success');
        $this->assertDatabaseCount('stock_records', 1);
        $this->assertDatabaseHas('stock_records', ['id' => $record->id, 'opening_qty' => 1000, 'opening_transaction_id' => 0]);
        $response = $this->get(route('admin.stock-records.show', $record->id))->assertOk();
        $this->assertEquals(900, $response->viewData('ledgerQty'));
        $this->assertEquals(900, $response->viewData('transactions')->first()->running_qty);
        $response->assertSee('Count correction');
    }

    public function test_material_variants_and_size_breakdown_are_respected(): void
    {
        $orderId = $this->order('CU-BLUE', 100, 'pending', 'Blue');
        $order = DB::table('ocs')->find($orderId);
        $bomItem = DB::table('bom_items')->where('bom_header_id', $order->bom_header_id)->first();
        DB::table('customer_sizes')->insert(['id' => 1, 'size_name' => 'L']);
        DB::table('bom_item_customer_sizes')->insert(['bom_item_id' => $bomItem->id, 'customer_size_id' => 1]);
        DB::table('order_sizes')->insert(['cutsheet_id' => $orderId, 'size_name' => 'L', 'quantity' => 30]);
        $plan = $this->plan();
        $this->assertEquals(30, $plan[0]->required);
        $this->assertEquals(30, $plan[0]->shortage);
        $this->assertEquals(970, $plan[0]->projected);
        DB::table('ocs')->where('id', $orderId)->update(['status' => 'cancelled']);
        $this->assertCount(0, $this->plan());
    }

    public function test_history_pagination_and_pretracking_movements_keep_correct_balances(): void
    {
        $ledger = app(InventoryLedgerService::class);
        $ledger->adjust(['balance_id' => 1, 'new_qty' => 900, 'reason' => 'Before tracking']);
        $record = $this->record();
        $this->assertEquals(900, $record->opening_qty);
        $this->assertGreaterThan(0, $record->opening_transaction_id);
        for ($i = 1; $i <= 51; $i++) {
            $ledger->adjust(['balance_id' => 1, 'new_qty' => 900 + $i, 'reason' => 'Adjustment ' . $i]);
        }
        $response = $this->get(route('admin.stock-records.show', ['id' => $record->id, 'history_page' => 2]))->assertOk();
        $this->assertEquals(951, $response->viewData('transactions')->first()->running_qty);
        $this->assertEquals(951, $response->viewData('ledgerQty'));
        $all = $this->get(route('admin.stock-records.show', ['id' => $record->id, 'history' => 'all']))->assertOk();
        $this->assertEquals(1000, $all->viewData('historyOpening'));
        $this->assertEquals(900, $all->viewData('transactions')->first()->running_qty);
        $this->assertEquals(52, $all->viewData('transactions')->total());
    }

    public function test_duplicate_bom_material_lines_do_not_deduct_issued_quantity_twice(): void
    {
        $id = $this->order('CU-DUPLICATE', 400);
        $item = (array) DB::table('bom_items')->first();
        unset($item['id']);
        DB::table('bom_items')->insert($item);
        $req = DB::table('material_requisitions')->insertGetId(['requisition_code' => 'REQ-DUP', 'cutsheet_id' => $id, 'status' => 'partial']);
        DB::table('requisition_items')->insert(['requisition_id' => $req, 'material_id' => 1, 'material_color' => 'Red', 'material_size' => 'M', 'requested_qty' => 800, 'issued_qty' => 300]);
        DB::table('inventory_balances')->where('id', 1)->update(['balance_qty' => 700]);
        $line = $this->plan()->first();
        $this->assertEquals(800, $line->required);
        $this->assertEquals(300, $line->issued);
        $this->assertEquals(500, $line->remaining);
        $this->assertEquals(200, $line->projected);
    }

    public function test_access_control_and_unrelated_priorities(): void
    {
        $record = $this->record();
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_PPIC]));
        $this->get(route('admin.stock-records.index'))->assertOk();
        $this->put(route('admin.stock-records.priorities', $record->id), ['revision' => 0, 'priorities' => [['cutsheet_id' => 999, 'sort_order' => 1]]])->assertSessionHasErrors('priorities');
        $this->assertDatabaseCount('stock_record_priorities', 0);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_WAREHOUSE]));
        $this->get(route('admin.stock-records.index'))->assertOk();
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_PROD]));
        $this->get(route('admin.stock-records.index'))->assertForbidden();
        $this->post(route('admin.stock-records.sync'))->assertForbidden();
    }

    private function prepareDelivery(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        (require database_path('migrations/2026_09_24_000002_create_delivery_bills.php'))->up();
        Schema::table('material_issues', function ($t) {
            $t->string('issue_code')->unique(); $t->date('issue_date'); $t->string('receiver_name')->nullable(); $t->string('status');
        });
        Schema::create('issue_items', function ($t) {
            $t->id(); foreach (['issue_id', 'requisition_item_id', 'material_id'] as $field) $t->unsignedBigInteger($field);
            foreach (['material_color', 'material_size', 'lot_roll_no', 'location'] as $field) $t->string($field)->nullable();
            $t->unsignedBigInteger('location_id')->nullable(); $t->decimal('issued_qty', 14, 4); $t->timestamps();
        });
    }

    private function deliveryPayload(int $id, float $qty = 100): array
    {
        return ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'number' => 'XK09.2026.032',
            'customer' => 'Customer', 'address' => 'Delivery address', 'reason' => 'Production', 'shipper' => 'Shipper', 'shipper_address' => 'Shipper address',
            'items' => [['bom_item_id' => DB::table('bom_items')->where('bom_header_id', DB::table('ocs')->where('id', $id)->value('bom_header_id'))->value('id'), 'balance_id' => 1, 'quantity' => $qty]]];
    }

    public function test_delivery_priority_override_excel_and_retry_do_not_double_issue(): void
    {
        $this->prepareDelivery();
        $first = $this->order('CU-FIRST', 100);
        $second = $this->order('CU-SECOND', 100);
        DB::table('inventory_balances')->update(['balance_qty' => 150]);
        $record = $this->record();
        DB::table('stock_record_priorities')->insert([
            ['stock_record_id' => $record->id, 'cutsheet_id' => $first, 'sort_order' => 1],
            ['stock_record_id' => $record->id, 'cutsheet_id' => $second, 'sort_order' => 2],
        ]);
        $data = $this->deliveryPayload($second);
        $url = route('admin.norm.delivery-bills.store', $second);
        $this->get(route('admin.norm.delivery-bills', $second))->assertOk()->assertSee('Export Excel');
        $this->post($url, $data)->assertSessionHasErrors('priority_reason');
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('material_issues', 0);
        $this->assertDatabaseHas('inventory_balances', ['balance_qty' => 150]);
        $data['priority_reason'] = 'Urgent customer shipment';
        $this->post($url, $data)->assertOk()->assertDownload();
        $bill = DB::table('delivery_bills')->first();
        $this->assertDatabaseHas('inventory_balances', ['balance_qty' => 50]);
        $this->assertDatabaseHas('inventory_transactions', ['quantity' => -100, 'reference_doc' => $data['number']]);
        $this->assertEquals(100, DB::table('requisition_items')->sum('issued_qty'));
        $this->assertStringContainsString('CU-FIRST', $bill->priority_snapshot);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load(\Illuminate\Support\Facades\Storage::disk('local')->path($bill->file_path));
        $sheet = $book->getSheetByName('DELI');
        $this->assertSame($data['number'], $sheet->getCell('E2')->getValue());
        $this->assertSame('FAB-01', $sheet->getCell('B11')->getValue());
        $this->assertEquals(100, $sheet->getCell('G11')->getValue());
        $this->assertNull($sheet->getCell('B12')->getValue());
        $this->assertSame('Accountant', $sheet->getCell('A22')->getValue());
        $book->disconnectWorksheets();
        $this->post($url, $data)->assertOk()->assertDownload();
        $this->get(route('admin.norm.delivery-bills.download', [$second, $bill->id]))->assertOk()->assertDownload();
        $this->assertDatabaseCount('delivery_bills', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->get(route('admin.norm.delivery-bills.download', [$first, $bill->id]))->assertNotFound();
    }

    public function test_delivery_sufficient_stock_long_excel_and_formula_safe_text(): void
    {
        $this->prepareDelivery();
        $this->travelTo(\Carbon\Carbon::parse('2026-09-24 20:00:00', 'UTC'));
        $this->order('CU-FIRST', 100);
        $id = $this->order('CU-SECOND', 100);
        $data = $this->deliveryPayload($id, 1);
        $data['customer'] = '=1+1';
        $data['items'] = array_fill(0, 12, $data['items'][0]);
        $this->post(route('admin.norm.delivery-bills.store', $id), $data)->assertOk()->assertDownload();
        $bill = DB::table('delivery_bills')->first();
        $this->assertSame('2026-09-25', $bill->issued_on);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load(\Illuminate\Support\Facades\Storage::disk('local')->path($bill->file_path));
        $sheet = $book->getSheetByName('DELI');
        $this->assertSame('s', $sheet->getCell('B6')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('B6')->getValue());
        $this->assertEquals(12, $sheet->getCell('A22')->getValue());
        $this->assertSame('Accountant', $sheet->getCell('A24')->getValue());
        $this->assertEquals(988, DB::table('inventory_balances')->value('balance_qty'));
        $book->disconnectWorksheets();
    }

    public function test_delivery_failure_and_insufficient_stock_rollback_everything(): void
    {
        $this->prepareDelivery();
        $id = $this->order('CU-FAIL', 100);
        $data = $this->deliveryPayload($id, 600);
        $data['items'][] = $data['items'][0];
        $this->post(route('admin.norm.delivery-bills.store', $id), $data)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->mock(\App\Services\DeliveryBillExcelService::class)->shouldReceive('write')->once()->andThrow(new \RuntimeException('Disk full'));
        $this->post(route('admin.norm.delivery-bills.store', $id), $this->deliveryPayload($id))->assertSessionHas('error');
        $this->assertDatabaseCount('material_issues', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('delivery_bills', 0);
        $this->assertEquals(1000, DB::table('inventory_balances')->value('balance_qty'));
    }

    public function test_delivery_can_exceed_norm_and_existing_requisition_then_issue_again(): void
    {
        $this->prepareDelivery();
        $id = $this->order('CU-EXTRA', 100);
        $req = DB::table('material_requisitions')->insertGetId(['requisition_code' => 'REQ-EXTRA', 'cutsheet_id' => $id, 'status' => 'pending']);
        $item = DB::table('requisition_items')->insertGetId(['requisition_id' => $req, 'material_id' => 1, 'material_color' => 'Red', 'material_size' => 'M', 'requested_qty' => 100]);
        DB::table('inventory_balances')->update(['reserved_qty' => 100]);
        DB::table('inventory_reservations')->insert(['requisition_item_id' => $item, 'inventory_balance_id' => 1, 'reserved_qty' => 100]);
        $data = $this->deliveryPayload($id, 120);
        $url = route('admin.norm.delivery-bills.store', $id);
        $this->post($url, $data)->assertOk()->assertDownload();
        $this->assertDatabaseHas('requisition_items', ['id' => $item, 'requested_qty' => 120, 'issued_qty' => 120]);
        $this->assertDatabaseHas('inventory_balances', ['balance_qty' => 880, 'reserved_qty' => 0]);
        $this->assertEquals(100, DB::table('order_material_requirements')->where('cutsheet_id', $id)->value('required_qty'));
        $data = $this->deliveryPayload($id, 5);
        $data['number'] = 'XK-EXTRA-SECOND';
        $this->post($url, $data)->assertOk()->assertDownload();
        $this->assertEquals(125, DB::table('requisition_items')->sum('issued_qty'));
        $this->assertEquals(875, DB::table('inventory_balances')->value('balance_qty'));
        $this->post($url, $data)->assertOk()->assertDownload();
        $this->assertEquals(875, DB::table('inventory_balances')->value('balance_qty'));
    }

    public function test_delivery_uses_own_reservation_but_cannot_take_another_cus_stock(): void
    {
        $this->prepareDelivery();
        $first = $this->order('CU-FIRST', 100);
        $id = $this->order('CU-OWN', 100);
        DB::table('inventory_balances')->update(['balance_qty' => 150, 'reserved_qty' => 150]);
        foreach ([$first => 90, $id => 60] as $cu => $qty) {
            $req = DB::table('material_requisitions')->insertGetId(['requisition_code' => 'REQ-'.$cu, 'cutsheet_id' => $cu, 'status' => 'pending']);
            $item = DB::table('requisition_items')->insertGetId(['requisition_id' => $req, 'material_id' => 1, 'material_color' => 'Red', 'material_size' => 'M', 'requested_qty' => 100]);
            DB::table('inventory_reservations')->insert(['requisition_item_id' => $item, 'inventory_balance_id' => 1, 'reserved_qty' => $qty]);
        }
        $data = $this->deliveryPayload($id, 80);
        $data['priority_reason'] = 'Urgent';
        $this->post(route('admin.norm.delivery-bills.store', $id), $data)->assertSessionHasErrors('items');
        $data['items'][0]['quantity'] = 60;
        unset($data['priority_reason']);
        $this->post(route('admin.norm.delivery-bills.store', $id), $data)->assertOk()->assertDownload();
        $this->assertDatabaseHas('inventory_balances', ['balance_qty' => 90, 'reserved_qty' => 90]);
        $this->actingAs($this->createUserRecord(['role' => User::ROLE_PPIC]));
        $this->post(route('admin.norm.delivery-bills.store', $id), $this->deliveryPayload($id, 1))->assertForbidden();
    }
}
