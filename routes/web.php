<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BOMController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterPlanController;
use App\Http\Controllers\OCSController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\ShopFloorController;
use App\Http\Controllers\MRPController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MpsScheduleController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/
Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Auth (guest)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard chung
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // View-only pages by role
    Route::get('/order-cutsheet', [OCSController::class, 'index'])
        ->middleware('role:admin')
        ->name('ordercutsheet.view');

    Route::get('/order-cutsheet/export', [OCSController::class, 'export'])
        ->middleware('role:admin')
        ->name('ordercutsheet.export');

    Route::get('/master-plan', [MasterPlanController::class, 'index'])
        ->middleware('role:admin,ie,warehouse,ppic,prod,accountant')
        ->name('masterplan.view');

    Route::get('/master-plan/export', [MasterPlanController::class, 'export'])
        ->middleware('role:admin,ie,warehouse')
        ->name('masterplan.export');

    Route::get('/bom', [BOMController::class, 'index'])
        ->middleware('role:admin,ie')
        ->name('bom.view');

    Route::get('/revenue-view', [RevenueController::class, 'index'])
        ->middleware('role:admin,ie,prod')
        ->name('revenue.view');

    Route::get('/revenue-view/export', [RevenueController::class, 'export'])
        ->middleware('role:admin,ie,prod')
        ->name('revenue.export');

    Route::get('/revenue/sewing-lines/{cs}', [RevenueController::class, 'getSewingLinesByCs'])
        ->middleware('role:admin,ie,prod')
        ->name('revenue.sewing-lines');

    Route::get('/revenue/distribution', [RevenueController::class, 'getDistributionByCsAndLine'])
        ->middleware('role:admin,ie,prod')
        ->name('revenue.distribution');

    Route::get('/revenue/daily', [RevenueController::class, 'dailyRevenue'])
        ->middleware('role:admin,ie,prod')
        ->name('revenue.daily.line');

    Route::get('/revenue/daily-summary', [RevenueController::class, 'dailyRevenueSummary'])
        ->middleware('role:admin,ie,prod')
        ->name('revenue.daily.summary');

    Route::get('/revenue/monthly-report', [RevenueController::class, 'monthlyReport'])
        ->middleware('role:admin,ie,prod')
        ->name('revenue.monthly-report');

    Route::post('/revenue/daily', [RevenueController::class, 'storeDailyRevenue'])
        ->middleware('role:admin,prod')
        ->name('revenue.daily.store');

    Route::post('/revenue/daily/matrix', [RevenueController::class, 'storeDailyRevenueMatrix'])
        ->middleware('role:admin,prod')
        ->name('revenue.daily.matrix.store');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Fabric-to-Trim edit scope for admin + ppic
    Route::middleware('role:admin,ppic')->group(function () {
        Route::get('/master-plan/fabric/{id}/edit', [MasterPlanController::class, 'editFabric'])
            ->name('masterplan.fabric.edit');

        Route::put('/master-plan/fabric/{id}', [MasterPlanController::class, 'updateFabric'])
            ->name('masterplan.fabric.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin (prefix + role)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware('module.access')->group(function () {

        // Trang dashboard admin riêng (nếu cần)
        Route::get('/dashboard', [AuthController::class, 'adminDashboard'])->name('dashboard');
        Route::get('audit-trails', [AuditTrailController::class, 'index'])->name('audit-trails.index');
        Route::get('master-data/customers', [MasterDataController::class, 'customers'])->name('master-data.customers');
        Route::post('master-data/customers', [MasterDataController::class, 'storeCustomer'])->name('master-data.customers.store');
        Route::patch('master-data/customers/{id}', [MasterDataController::class, 'updateCustomer'])->name('master-data.customers.update');
        Route::delete('master-data/customers/{id}', [MasterDataController::class, 'destroyCustomer'])->name('master-data.customers.destroy');
        Route::get('master-data/materials', [MasterDataController::class, 'materials'])->name('master-data.materials');
        Route::post('master-data/materials', [MasterDataController::class, 'storeMaterial'])->name('master-data.materials.store');
        Route::patch('master-data/materials/{id}', [MasterDataController::class, 'updateMaterial'])->name('master-data.materials.update');
        Route::post('master-data/material-categories', [MasterDataController::class, 'storeMaterialCategory'])->name('master-data.material-categories.store');
        Route::patch('master-data/material-categories/{id}', [MasterDataController::class, 'updateMaterialCategory'])->name('master-data.material-categories.update');
        Route::delete('master-data/material-categories/{id}', [MasterDataController::class, 'destroyMaterialCategory'])->name('master-data.material-categories.destroy');
        Route::post('master-data/material-subcategories', [MasterDataController::class, 'storeMaterialSubcategory'])->name('master-data.material-subcategories.store');
        Route::patch('master-data/material-subcategories/{id}', [MasterDataController::class, 'updateMaterialSubcategory'])->name('master-data.material-subcategories.update');
        Route::delete('master-data/material-subcategories/{id}', [MasterDataController::class, 'destroyMaterialSubcategory'])->name('master-data.material-subcategories.destroy');
        Route::post('master-data/material-vendors', [MasterDataController::class, 'storeMaterialVendor'])->name('master-data.material-vendors.store');
        Route::delete('master-data/material-vendors/{id}', [MasterDataController::class, 'destroyMaterialVendor'])->name('master-data.material-vendors.destroy');

        // MasterPlan
        Route::resource('masterplan', MasterPlanController::class)->except(['show']);

        Route::get('ocs/export', [OCSController::class, 'export'])->name('ocs.export');

        // OCS
        Route::resource('ocs', OCSController::class)->except(['show']);
        Route::patch('ocs/{id}/status', [OCSController::class, 'updateStatus'])->name('ocs.status');
        Route::post('ocs/import', [OCSController::class, 'import'])->name('ocs.import');

        Route::get('revenue/export', [RevenueController::class, 'export'])->name('revenue.export');

        // Revenue
        Route::resource('revenue', RevenueController::class)->except(['show']);

        Route::get('holidays/export', [HolidayController::class, 'export'])->name('holidays.export');

        // Holidays
        Route::resource('holidays', HolidayController::class);

        // Colors (Line master)
        Route::resource('colors', ColorController::class)->except(['show']);

        // BOM
        Route::get('bom/export/{id}', [BOMController::class, 'export'])->name('bom.export');
        Route::post('bom/import-preview', [BOMController::class, 'importPreview'])->name('bom.import-preview');
        Route::post('bom/import-store', [BOMController::class, 'importStore'])->name('bom.import-store');
        Route::post('bom/{bom}/clone', [BOMController::class, 'clone'])->name('bom.clone');
        Route::post('bom/{bom}/tech-pack', [BOMController::class, 'saveTechPack'])->name('bom.tech-pack.save');
        Route::post('bom/{bom}/colorways', [BOMController::class, 'saveColorways'])->name('bom.colorways.save');
        Route::resource('bom', BOMController::class);

        // Shop Floor Control
        Route::get('shopfloor/dashboard', [ShopFloorController::class, 'dashboard'])->name('shopfloor.dashboard');
        Route::get('shopfloor/wip', [ShopFloorController::class, 'wipReport'])->name('shopfloor.wip');
        Route::get('shopfloor/efficiency', [ShopFloorController::class, 'efficiencyReport'])->name('shopfloor.efficiency');
        Route::get('shopfloor/mps-logs', [ShopFloorController::class, 'mpsLogs'])->name('shopfloor.mps-logs');
        Route::get('shopfloor/downtime', [ShopFloorController::class, 'downtime'])->name('shopfloor.downtime');
        Route::post('shopfloor/downtime', [ShopFloorController::class, 'storeDowntime'])->name('shopfloor.downtime.store');
        Route::post('shopfloor/daily/store', [ShopFloorController::class, 'storeDaily'])->name('shopfloor.daily.store');
        Route::post('shopfloor/mps-log', [ShopFloorController::class, 'storeMpsLog'])->name('shopfloor.mps-log.store');
        Route::patch('shopfloor/{id}/status', [ShopFloorController::class, 'updateStatus'])->name('shopfloor.status');
        Route::get('shopfloor/create-from-mtp/{mtpId}', [ShopFloorController::class, 'createFromMtp'])->name('shopfloor.create-from-mtp');
        Route::resource('shopfloor', ShopFloorController::class)->only(['index', 'show']);
        Route::post('mps-schedules', [MpsScheduleController::class, 'store'])->name('mps-schedules.store');
        Route::patch('mps-schedules/{id}', [MpsScheduleController::class, 'update'])->name('mps-schedules.update');
        Route::delete('mps-schedules/{id}', [MpsScheduleController::class, 'destroy'])->name('mps-schedules.destroy');
        Route::get('production-planning', [MpsScheduleController::class, 'index'])->name('production-planning.index');
        Route::post('sewing-lines', [MpsScheduleController::class, 'storeLine'])->name('sewing-lines.store');
        Route::patch('sewing-lines/{id}', [MpsScheduleController::class, 'updateLine'])->name('sewing-lines.update');
        Route::delete('sewing-lines/{id}', [MpsScheduleController::class, 'destroyLine'])->name('sewing-lines.destroy');
        Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::post('work-orders/{id}/split', [WorkOrderController::class, 'split'])->name('work-orders.split');
        Route::patch('work-orders/{id}/status', [WorkOrderController::class, 'status'])->name('work-orders.status');

        // MRP
        Route::post('mrp/calculate', [MRPController::class, 'calculate'])->name('mrp.calculate');
        Route::get('mrp/create-po/{id}', [MRPController::class, 'createPoFromMrp'])->name('mrp.create-po');
        Route::resource('mrp', MRPController::class)->except(['store', 'edit', 'update']);

        // Procurement
        Route::get('procurement/suppliers', [ProcurementController::class, 'suppliers'])->name('procurement.suppliers');
        Route::post('procurement/suppliers', [ProcurementController::class, 'suppliersStore'])->name('procurement.suppliers.store');
        Route::patch('procurement/suppliers/{id}', [ProcurementController::class, 'supplierUpdate'])->name('procurement.suppliers.update');
        Route::get('procurement/create-from-mrp/{mrpId}', [ProcurementController::class, 'createFromMrp'])->name('procurement.create-from-mrp');
        Route::post('procurement/store', [ProcurementController::class, 'store'])->name('procurement.store');
        Route::post('procurement/from-suggestions', [ProcurementController::class, 'createFromSuggestions'])->name('procurement.from-suggestions');
        Route::patch('procurement/{id}/status', [ProcurementController::class, 'updateStatus'])->name('procurement.status');
        Route::patch('procurement/{id}/eta', [ProcurementController::class, 'updateEta'])->name('procurement.eta.update');
        Route::post('procurement/{id}/receipts', [ProcurementController::class, 'receive'])->name('procurement.receipts.store');
        Route::resource('procurement', ProcurementController::class)->except(['store', 'edit', 'update']);

        // Inventory
        Route::get('inventory/warehouses', [InventoryController::class, 'warehouses'])->name('inventory.warehouses');
        Route::post('inventory/warehouses', [InventoryController::class, 'warehousesStore'])->name('inventory.warehouses.store');
        Route::patch('inventory/warehouses/{id}', [InventoryController::class, 'warehouseUpdate'])->name('inventory.warehouses.update');
        Route::post('inventory/locations', [InventoryController::class, 'locationsStore'])->name('inventory.locations.store');
        Route::patch('inventory/locations/{id}', [InventoryController::class, 'locationUpdate'])->name('inventory.locations.update');
        Route::get('inventory/stock-counts', [InventoryController::class, 'stockCounts'])->name('inventory.stock-counts');
        Route::post('inventory/stock-counts', [InventoryController::class, 'storeStockCount'])->name('inventory.stock-counts.store');
        Route::post('inventory/stock-counts/{id}/approve', [InventoryController::class, 'approveStockCount'])->name('inventory.stock-counts.approve');
        Route::post('inventory/requisitions/{id}/cancel', [InventoryController::class, 'cancelRequisition'])->name('inventory.requisitions.cancel');
        Route::get('inventory/requisitions', [InventoryController::class, 'requisitions'])->name('inventory.requisitions');
        Route::get('inventory/transactions', [InventoryController::class, 'transactions'])->name('inventory.transactions');
        Route::get('inventory/report', [InventoryController::class, 'stockReport'])->name('inventory.report');
        Route::post('inventory/{id}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('inventory/issues', [InventoryController::class, 'issue'])->name('inventory.issues.store');
        Route::resource('inventory', InventoryController::class)->only(['index']);

        // Finance & Costing
        Route::get('finance/dashboard', [FinanceController::class, 'dashboard'])->name('finance.dashboard');
        Route::get('finance/cost-analysis', [FinanceController::class, 'costAnalysis'])->name('finance.cost-analysis');
        Route::get('finance/order-costings', [FinanceController::class, 'orderCostings'])->name('finance.order-costings');
        Route::get('finance/fob-costs', [FinanceController::class, 'fobCosts'])->name('finance.fob-costs');
        Route::post('finance/fob-costs', [FinanceController::class, 'storeFobCost'])->name('finance.fob-costs.store');
        Route::delete('finance/fob-costs/{id}', [FinanceController::class, 'deleteFobCost'])->name('finance.fob-costs.delete');
        Route::post('finance/cost-analysis/calculate', [FinanceController::class, 'calculateCostAnalysis'])->name('finance.cost-analysis.calculate');
        Route::get('finance/expenses', [FinanceController::class, 'expenses'])->name('finance.expenses');
        Route::post('finance/expenses', [FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
        Route::delete('finance/expenses/{id}', [FinanceController::class, 'deleteExpense'])->name('finance.expenses.delete');
        Route::get('finance/profitability', [FinanceController::class, 'profitability'])->name('finance.profitability');
        Route::get('finance/monthly', [FinanceController::class, 'monthlyReport'])->name('finance.monthly');
        Route::get('finance/revenue', [FinanceController::class, 'revenueReport'])->name('finance.revenue');
    });

    Route::get('/ocs-by-cs/{cs}', function ($cs) {
        $ocs = DB::table('ocs')->where('CS', $cs)->first();
        return response()->json($ocs);
    });

    Route::get('/get-cmt/{cs}', function ($cs) {
        $ocs = DB::table('ocs')->where('CS', $cs)->first();
        return response()->json($ocs);
    });

    Route::get('/calc-date', [MasterPlanController::class, 'calcDateAjax']);
});
