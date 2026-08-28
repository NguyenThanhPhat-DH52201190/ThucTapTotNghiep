<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InventoryLedgerService;
use App\Services\OrderCostSnapshotService;
use App\Services\RequisitionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class WorkflowExecutionTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
        $this->createWorkflowSchema();
    }

    public function test_release_requisition_reserves_stock_and_issue_cannot_exceed_reservation(): void
    {
        $bomId = DB::table('bom_headers')->insertGetId(['style_no' => 'S-001', 'version' => '1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->createOcsRecord(['bom_header_id' => $bomId, 'Qty' => 100, 'Color' => 'Green']);
        $cutsheetId = DB::table('ocs')->where('CS', 'CS-001')->value('id');
        $materialId = DB::table('materials')->insertGetId(['internal_code' => 'FAB-01', 'material_name' => 'Fabric', 'unit' => 'M', 'material_type' => 'fabric', 'created_at' => now(), 'updated_at' => now()]);
        $itemId = DB::table('bom_items')->insertGetId(['bom_header_id' => $bomId, 'material_id' => $materialId, 'material_code' => 'FAB-01', 'material_name' => 'Fabric', 'material_type' => 'fabric', 'colour' => 'Green', 'unit' => 'M', 'consumption_rate' => 0.5, 'waste_percent' => 0, 'total_cost' => 5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bom_colorways')->insert(['bom_item_id' => $itemId, 'garment_color' => 'Green', 'material_color' => 'Green', 'created_at' => now(), 'updated_at' => now()]);
        $balanceId = DB::table('inventory_balances')->insertGetId(['material_id' => $materialId, 'material_color' => 'Green', 'lot_roll_no' => 'ROLL-1', 'balance_qty' => 100, 'reserved_qty' => 0, 'unit_cost' => 8, 'created_at' => now(), 'updated_at' => now()]);

        $service = app(RequisitionService::class);
        $requisitionId = $service->createForCutsheet($cutsheetId);
        $this->assertSame($requisitionId, $service->createForCutsheet($cutsheetId));
        $requisitionItem = DB::table('requisition_items')->where('requisition_id', $requisitionId)->first();
        $this->assertSame(50.0, (float) $requisitionItem->requested_qty);
        $this->assertSame(50.0, (float) DB::table('inventory_balances')->where('id', $balanceId)->value('reserved_qty'));

        app(InventoryLedgerService::class)->issue(['balance_id' => $balanceId, 'requisition_item_id' => $requisitionItem->id, 'quantity' => 30, 'issue_id' => 1, 'issue_code' => 'ISS-1', 'issue_date' => now()->toDateString(), 'material_code' => 'FAB-01', 'unit' => 'M']);
        $this->assertSame(70.0, (float) DB::table('inventory_balances')->where('id', $balanceId)->value('balance_qty'));
        $this->assertSame(20.0, (float) DB::table('inventory_balances')->where('id', $balanceId)->value('reserved_qty'));

        try {
            app(InventoryLedgerService::class)->issue(['balance_id' => $balanceId, 'requisition_item_id' => $requisitionItem->id, 'quantity' => 21, 'issue_id' => 2, 'issue_code' => 'ISS-2', 'issue_date' => now()->toDateString(), 'material_code' => 'FAB-01', 'unit' => 'M']);
            $this->fail('Issue above reserved quantity must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reserved', $exception->getMessage());
        }
        $this->assertSame(70.0, (float) DB::table('inventory_balances')->where('id', $balanceId)->value('balance_qty'));
    }

    public function test_mps_schedule_rejects_capacity_above_line_limit(): void
    {
        $this->createOcsRecord();
        $cutsheetId = DB::table('ocs')->where('CS', 'CS-001')->value('id');
        $lineId = DB::table('sewing_lines')->insertGetId(['line_code' => 'L1', 'line_name' => 'Line 1', 'default_workers' => 1, 'working_hours_per_day' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $workOrderId = DB::table('work_orders')->insertGetId(['wo_number' => 'WO-1', 'cutsheet_id' => $cutsheetId, 'planned_qty' => 100, 'smv' => 10, 'status' => 'released', 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->createUserRecord(['role' => User::ROLE_PPIC]);

        $this->actingAs($user)->postJson('/admin/mps-schedules', ['work_order_id' => $workOrderId, 'line_id' => $lineId, 'planned_start_date' => '2026-08-26', 'planned_end_date' => '2026-08-26', 'daily_target_qty' => 7])
            ->assertUnprocessable()->assertJsonValidationErrors('daily_target_qty');
        $this->assertDatabaseCount('mps_schedules', 0);
    }

    public function test_partial_po_receipt_posts_lot_stock_and_keeps_po_open(): void
    {
        $materialId = DB::table('materials')->insertGetId(['internal_code' => 'FAB-01', 'material_name' => 'Fabric', 'unit' => 'M', 'material_type' => 'fabric', 'created_at' => now(), 'updated_at' => now()]);
        $warehouseId = DB::table('warehouses')->insertGetId(['name' => 'Raw Material', 'type' => 'raw_material', 'created_at' => now(), 'updated_at' => now()]);
        $locationId = DB::table('locations')->insertGetId(['warehouse_id' => $warehouseId, 'code' => 'A-01', 'created_at' => now(), 'updated_at' => now()]);
        $poId = DB::table('purchase_orders')->insertGetId(['po_number' => 'PO-1', 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now()]);
        $poItemId = DB::table('po_items')->insertGetId(['po_id' => $poId, 'material_id' => $materialId, 'material_code' => 'FAB-01', 'material_name' => 'Fabric', 'color' => 'Green', 'unit' => 'M', 'quantity' => 10, 'received_qty' => 0, 'unit_price' => 8, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->createUserRecord(['role' => User::ROLE_PPIC]);

        $this->actingAs($user)->post("/admin/procurement/{$poId}/receipts", ['received_date' => '2026-08-26', 'warehouse_id' => $warehouseId, 'location_id' => $locationId, 'items' => [['po_item_id' => $poItemId, 'quantity' => 4, 'lot_roll_no' => 'ROLL-1']]])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('po_items', ['id' => $poItemId, 'received_qty' => 4, 'status' => 'partial']);
        $this->assertDatabaseHas('inventory_balances', ['material_id' => $materialId, 'lot_roll_no' => 'ROLL-1', 'balance_qty' => 4]);
        $this->assertDatabaseHas('audit_trails', ['event_type' => 'goods_received', 'entity_type' => 'purchase_order', 'entity_id' => $poId]);
    }

    public function test_shop_floor_blocks_output_above_preceding_wip_and_records_actual_labor(): void
    {
        $this->createOcsRecord();
        $cutsheetId = DB::table('ocs')->where('CS', 'CS-001')->value('id');
        $lineId = DB::table('sewing_lines')->insertGetId(['line_code' => 'L1', 'line_name' => 'Line 1', 'default_workers' => 10, 'working_hours_per_day' => 8, 'created_at' => now(), 'updated_at' => now()]);
        $workOrderId = DB::table('work_orders')->insertGetId(['wo_number' => 'WO-1', 'cutsheet_id' => $cutsheetId, 'planned_qty' => 100, 'smv' => 5, 'status' => 'in_production', 'created_at' => now(), 'updated_at' => now()]);
        $scheduleId = DB::table('mps_schedules')->insertGetId(['work_order_id' => $workOrderId, 'line_id' => $lineId, 'planned_start_date' => '2026-08-26', 'planned_end_date' => '2026-08-26', 'daily_target_qty' => 100, 'material_eta_status' => 'ready', 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->createUserRecord(['role' => User::ROLE_PROD]);
        $base = ['mps_schedule_id' => $scheduleId, 'log_date' => '2026-08-26', 'target_qty' => 100, 'defect_qty' => 0, 'actual_workers' => 2, 'working_hours' => 8, 'labor_rate' => 10];

        $this->actingAs($user)->post('/admin/shopfloor/mps-log', $base + ['operation_stage' => 'cut', 'actual_qty' => 100])->assertSessionHas('success');
        $this->actingAs($user)->post('/admin/shopfloor/mps-log', $base + ['operation_stage' => 'sewing', 'actual_qty' => 101])->assertSessionHas('error');
        $this->assertDatabaseMissing('daily_production_logs', ['mps_schedule_id' => $scheduleId, 'operation_stage' => 'sewing']);

        $this->actingAs($user)->post('/admin/shopfloor/mps-log', $base + ['operation_stage' => 'sewing', 'actual_qty' => 80, 'details' => [['color' => 'Green', 'size_name' => 'M', 'quantity' => 50], ['color' => 'Green', 'size_name' => 'L', 'quantity' => 30]]])->assertSessionHas('success');
        $this->assertDatabaseHas('daily_production_logs', ['mps_schedule_id' => $scheduleId, 'operation_stage' => 'sewing', 'actual_labor_cost' => 160]);
        $this->assertDatabaseHas('production_log_details', ['color' => 'Green', 'size_name' => 'M', 'quantity' => 50]);
    }

    public function test_fob_snapshot_includes_other_costs_and_respects_customer_material_ownership(): void
    {
        $bomId = DB::table('bom_headers')->insertGetId(['style_no' => 'S-001', 'version' => '1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->createOcsRecord(['bom_header_id' => $bomId, 'Qty' => 10, 'CMT' => 3, 'order_type' => 'fob', 'material_ownership' => 'customer', 'unit_price' => 20]);
        $cutsheetId = DB::table('ocs')->where('CS', 'CS-001')->value('id');
        DB::table('bom_items')->insert(['bom_header_id' => $bomId, 'material_code' => 'FAB-01', 'material_name' => 'Fabric', 'material_type' => 'fabric', 'unit' => 'M', 'consumption_rate' => 1, 'waste_percent' => 0, 'total_cost' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $lineId = DB::table('sewing_lines')->insertGetId(['line_code' => 'L1', 'line_name' => 'Line 1', 'default_workers' => 1, 'working_hours_per_day' => 8, 'created_at' => now(), 'updated_at' => now()]);
        $workOrderId = DB::table('work_orders')->insertGetId(['wo_number' => 'WO-1', 'cutsheet_id' => $cutsheetId, 'planned_qty' => 10, 'smv' => 5, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $scheduleId = DB::table('mps_schedules')->insertGetId(['work_order_id' => $workOrderId, 'line_id' => $lineId, 'daily_target_qty' => 10, 'material_eta_status' => 'ready', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('daily_production_logs')->insert(['mps_schedule_id' => $scheduleId, 'log_date' => '2026-08-26', 'operation_stage' => 'sewing', 'target_qty' => 10, 'actual_qty' => 10, 'defect_qty' => 0, 'actual_workers' => 1, 'working_hours' => 4, 'labor_rate' => 10, 'actual_labor_cost' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_cost_components')->insert([['cutsheet_id' => $cutsheetId, 'cost_type' => 'freight', 'cost_stage' => 'estimated', 'amount' => 10, 'created_at' => now(), 'updated_at' => now()], ['cutsheet_id' => $cutsheetId, 'cost_type' => 'packing', 'cost_stage' => 'actual', 'amount' => 15, 'created_at' => now(), 'updated_at' => now()]]);

        app(OrderCostSnapshotService::class)->snapshot($cutsheetId);
        $this->assertDatabaseHas('order_costings', ['cutsheet_id' => $cutsheetId, 'est_material_cost' => 0, 'actual_material_cost' => 0, 'actual_labor_cost' => 40, 'actual_other_cost' => 15, 'revenue' => 200, 'final_profit' => 145]);
    }

    private function createWorkflowSchema(): void
    {
        Schema::table('ocs', function (Blueprint $table): void { $table->string('order_type')->default('cmt'); $table->string('material_ownership')->default('factory'); $table->decimal('unit_price', 14, 2)->default(0); });
        Schema::create('materials', function (Blueprint $table): void { $table->id(); $table->string('internal_code')->unique(); $table->string('material_name'); $table->string('color')->nullable(); $table->string('size')->nullable(); $table->string('unit')->default('M'); $table->string('material_type')->default('fabric'); $table->timestamps(); });
        Schema::create('bom_items', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('bom_header_id'); $table->unsignedBigInteger('material_id')->nullable(); $table->string('material_code'); $table->string('material_name'); $table->string('material_type'); $table->string('colour')->nullable(); $table->string('size')->nullable(); $table->string('unit')->default('M'); $table->decimal('consumption_rate', 12, 4)->default(0); $table->decimal('waste_percent', 8, 2)->default(0); $table->decimal('total_cost', 14, 2)->default(0); $table->timestamps(); });
        Schema::create('bom_colorways', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('bom_item_id'); $table->string('garment_color'); $table->string('material_color'); $table->timestamps(); });
        Schema::create('material_requisitions', function (Blueprint $table): void { $table->id(); $table->string('requisition_code'); $table->unsignedBigInteger('cutsheet_id'); $table->string('status'); $table->timestamps(); });
        Schema::create('requisition_items', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('requisition_id'); $table->unsignedBigInteger('material_id'); $table->string('material_color')->nullable(); $table->string('material_size')->nullable(); $table->decimal('requested_qty', 14, 4); $table->decimal('issued_qty', 14, 4)->default(0); $table->timestamps(); });
        Schema::create('inventory_balances', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('material_id'); $table->unsignedBigInteger('warehouse_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->string('material_color')->nullable(); $table->string('material_size')->nullable(); $table->string('lot_roll_no')->nullable(); $table->string('location')->nullable(); $table->decimal('balance_qty', 14, 4)->default(0); $table->decimal('reserved_qty', 14, 4)->default(0); $table->decimal('unit_cost', 14, 2)->default(0); $table->timestamps(); });
        Schema::create('inventory_reservations', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('requisition_item_id'); $table->unsignedBigInteger('inventory_balance_id'); $table->decimal('reserved_qty', 14, 4); $table->decimal('consumed_qty', 14, 4)->default(0); $table->string('status')->default('active'); $table->timestamps(); });
        Schema::create('inventory_transactions', function (Blueprint $table): void { $table->id(); $table->string('transaction_type'); $table->string('reference_type'); $table->unsignedBigInteger('reference_id')->nullable(); $table->string('reference_doc')->nullable(); $table->date('transaction_date'); $table->unsignedBigInteger('material_id'); $table->string('material_code'); $table->string('material_color')->nullable(); $table->string('material_size')->nullable(); $table->string('lot_roll_no')->nullable(); $table->string('location')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->decimal('quantity', 14, 4); $table->string('unit'); $table->unsignedBigInteger('from_warehouse_id')->nullable(); $table->unsignedBigInteger('to_warehouse_id')->nullable(); $table->decimal('unit_cost', 14, 2); $table->decimal('total_cost', 14, 2); $table->text('notes')->nullable(); $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps(); });
        Schema::create('sewing_lines', function (Blueprint $table): void { $table->id(); $table->string('line_code'); $table->string('line_name'); $table->unsignedInteger('default_workers'); $table->decimal('working_hours_per_day', 5, 2)->default(8); $table->timestamps(); });
        Schema::create('work_orders', function (Blueprint $table): void { $table->id(); $table->string('wo_number'); $table->unsignedBigInteger('cutsheet_id'); $table->unsignedInteger('planned_qty'); $table->decimal('smv', 10, 2); $table->string('status'); $table->timestamps(); });
        Schema::create('mps_schedules', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('work_order_id'); $table->unsignedBigInteger('line_id'); $table->date('planned_start_date')->nullable(); $table->date('planned_end_date')->nullable(); $table->unsignedInteger('daily_target_qty')->default(0); $table->string('material_eta_status')->default('unknown'); $table->timestamps(); });
        Schema::create('daily_production_logs', function (Blueprint $table): void { $table->id(); $table->date('log_date'); $table->unsignedBigInteger('mps_schedule_id'); $table->string('operation_stage'); $table->unsignedInteger('target_qty'); $table->unsignedInteger('actual_qty'); $table->unsignedInteger('defect_qty')->default(0); $table->unsignedInteger('actual_workers')->default(0); $table->decimal('working_hours', 5, 2)->default(0); $table->decimal('labor_rate', 12, 2)->default(0); $table->decimal('actual_labor_cost', 14, 2)->default(0); $table->timestamps(); $table->unique(['mps_schedule_id', 'log_date', 'operation_stage']); });
        Schema::create('production_log_details', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('log_id'); $table->string('color')->nullable(); $table->string('size_name')->nullable(); $table->unsignedInteger('quantity'); $table->timestamps(); });
        Schema::create('mrp_suggestions', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('cutsheet_id'); $table->timestamps(); });
        Schema::create('warehouses', function (Blueprint $table): void { $table->id(); $table->string('name'); $table->string('type')->nullable(); $table->timestamps(); });
        Schema::create('locations', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('warehouse_id'); $table->string('code'); $table->timestamps(); });
        Schema::create('purchase_orders', function (Blueprint $table): void { $table->id(); $table->string('po_number'); $table->string('status'); $table->timestamps(); });
        Schema::create('po_items', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('po_id'); $table->unsignedBigInteger('mrp_suggestion_id')->nullable(); $table->unsignedBigInteger('material_id')->nullable(); $table->string('material_code'); $table->string('material_name'); $table->string('color')->nullable(); $table->string('unit'); $table->decimal('quantity', 14, 4)->default(0); $table->decimal('received_qty', 14, 4)->default(0); $table->decimal('unit_price', 14, 2)->default(0); $table->string('status')->default('pending'); $table->date('expected_date')->nullable(); $table->timestamps(); });
        Schema::create('po_receipts', function (Blueprint $table): void { $table->id(); $table->string('receipt_number'); $table->unsignedBigInteger('po_id'); $table->date('received_date'); $table->string('reference_number')->nullable(); $table->text('notes')->nullable(); $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps(); });
        Schema::create('po_receipt_items', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('po_receipt_id'); $table->unsignedBigInteger('po_item_id'); $table->string('material_code'); $table->decimal('quantity_received', 14, 4); $table->string('batch_no')->nullable(); $table->unsignedBigInteger('warehouse_id'); $table->unsignedBigInteger('location_id')->nullable(); $table->unsignedBigInteger('material_id')->nullable(); $table->timestamps(); });
        Schema::create('material_issues', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('requisition_id'); $table->timestamps(); });
        Schema::create('order_cost_components', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('cutsheet_id'); $table->string('cost_type'); $table->string('cost_stage'); $table->decimal('amount', 14, 2); $table->timestamps(); });
        Schema::create('order_costings', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('cutsheet_id')->unique(); $table->decimal('est_material_cost', 14, 2); $table->decimal('est_labor_cost', 14, 2); $table->decimal('est_other_cost', 14, 2); $table->decimal('actual_material_cost', 14, 2); $table->decimal('actual_labor_cost', 14, 2); $table->decimal('actual_other_cost', 14, 2); $table->decimal('revenue', 14, 2); $table->decimal('material_variance', 14, 2); $table->decimal('labor_variance', 14, 2); $table->decimal('other_variance', 14, 2); $table->decimal('total_variance', 14, 2); $table->decimal('final_profit', 14, 2); $table->timestamps(); });
        Schema::create('audit_trails', function (Blueprint $table): void { $table->id(); $table->string('event_type'); $table->string('entity_type'); $table->unsignedBigInteger('entity_id')->nullable(); $table->unsignedBigInteger('user_id')->nullable(); $table->text('before_data')->nullable(); $table->text('after_data')->nullable(); $table->string('reason')->nullable(); $table->timestamps(); });
    }
}
