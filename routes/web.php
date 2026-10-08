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
use App\Http\Controllers\NormController;
use App\Http\Controllers\DevelopmentNormController;
use App\Http\Controllers\StockRecordController;
use App\Http\Controllers\UserManagementController;
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
        ->middleware('role:admin,ie,warehouse,ppic,prod,accountant,qa_qc')
        ->name('masterplan.view');

    Route::get('/master-plan/confirm-date', [MasterPlanController::class, 'confirmDate'])
        ->middleware('role:admin,ie,warehouse,ppic,prod,accountant,qa_qc')
        ->name('masterplan.confirm-date');

    Route::put('/master-plan/accountant/{id}/note', [MasterPlanController::class, 'updateAccountantNote'])
        ->middleware('role:accountant')
        ->name('masterplan.accountant.note.update');

    Route::get('/master-plan/export', [MasterPlanController::class, 'export'])
        ->middleware('role:admin,ie,warehouse,qa_qc')
        ->name('masterplan.export');

    Route::get('/master-plan/warehouse/{id}/edit', [MasterPlanController::class, 'editWarehouse'])
        ->middleware('role:warehouse')
        ->name('masterplan.warehouse.edit');

    Route::put('/master-plan/warehouse/{id}', [MasterPlanController::class, 'updateWarehouse'])
        ->middleware('role:warehouse')
        ->name('masterplan.warehouse.update');

    Route::put('/master-plan/qa-qc/{id}', [MasterPlanController::class, 'updateQaQc'])
        ->middleware('role:admin,qa_qc')
        ->name('masterplan.qa-qc.update');

    Route::get('/master-plan/qa-qc/{id}/edit', [MasterPlanController::class, 'editQaQc'])
        ->middleware('role:admin,qa_qc')
        ->name('masterplan.qa-qc.edit');

    Route::get('/master-plan/ocs/{id}/image', [OCSController::class, 'image'])
        ->middleware('role:admin,ie,warehouse,ppic,prod,accountant')
        ->name('masterplan.ocs-image');

    Route::get('/customer-styles/{style}/image', [\App\Http\Controllers\CustomerStyleController::class, 'image'])->middleware('role:admin,ie')->name('customer-styles.image');

    Route::get('/bom', [BOMController::class, 'index'])
        ->middleware('role:admin,user,warehouse,ppic,ie,prod,accountant,development')
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

        Route::get('users', [UserManagementController::class, 'index'])->middleware('role:admin')->name('users.index');
        Route::post('users', [UserManagementController::class, 'store'])->middleware('role:admin')->name('users.store');
        Route::patch('users/{user}', [UserManagementController::class, 'update'])->middleware('role:admin')->name('users.update');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->middleware('role:admin')->name('users.destroy');

        // Trang dashboard admin riêng (nếu cần)
        Route::get('/dashboard', [AuthController::class, 'adminDashboard'])->name('dashboard');
        Route::get('audit-trails', [AuditTrailController::class, 'index'])->name('audit-trails.index');
        Route::get('master-data/customers/{customer}/styles', [\App\Http\Controllers\CustomerStyleController::class, 'index'])->name('customer-styles.index');
        Route::post('master-data/customers/{customer}/styles', [\App\Http\Controllers\CustomerStyleController::class, 'save'])->name('customer-styles.store');
        Route::put('master-data/customers/{customer}/styles/{style}', [\App\Http\Controllers\CustomerStyleController::class, 'save'])->name('customer-styles.update');
        Route::get('master-data/customers', [MasterDataController::class, 'customers'])->name('master-data.customers');
        Route::post('master-data/customers', [MasterDataController::class, 'storeCustomer'])->name('master-data.customers.store');
        Route::patch('master-data/customers/{id}', [MasterDataController::class, 'updateCustomer'])->name('master-data.customers.update');
        Route::delete('master-data/customers/{id}', [MasterDataController::class, 'destroyCustomer'])->name('master-data.customers.destroy');
        Route::get('master-data/customer-sizes', [MasterDataController::class, 'customerSizes'])->name('master-data.customer-sizes');
        Route::put('master-data/customer-sizes/{id}', [MasterDataController::class, 'saveCustomerSizes'])->name('master-data.customer-sizes.save');
        Route::get('master-data/materials', [MasterDataController::class, 'materials'])->name('master-data.materials');
        Route::get('master-data/materials/{id}/image', [MasterDataController::class, 'materialImage'])->name('master-data.material-image');
        Route::post('master-data/materials', [MasterDataController::class, 'storeMaterial'])->name('master-data.materials.store');
        Route::get('master-data/materials/copy', [\App\Http\Controllers\MaterialCopyController::class, 'create'])->name('master-data.materials.copy');
        Route::post('master-data/materials/copy', [\App\Http\Controllers\MaterialCopyController::class, 'store'])->name('master-data.materials.copy.store');
        Route::patch('master-data/materials/{id}', [MasterDataController::class, 'updateMaterial'])->name('master-data.materials.update');
        Route::post('master-data/material-categories', [MasterDataController::class, 'storeMaterialCategory'])->name('master-data.material-categories.store');
        Route::patch('master-data/material-categories/{id}', [MasterDataController::class, 'updateMaterialCategory'])->name('master-data.material-categories.update');
        Route::delete('master-data/material-categories/{id}', [MasterDataController::class, 'destroyMaterialCategory'])->name('master-data.material-categories.destroy');
        Route::post('master-data/material-subcategories', [MasterDataController::class, 'storeMaterialSubcategory'])->name('master-data.material-subcategories.store');
        Route::patch('master-data/material-subcategories/{id}', [MasterDataController::class, 'updateMaterialSubcategory'])->name('master-data.material-subcategories.update');
        Route::delete('master-data/material-subcategories/{id}', [MasterDataController::class, 'destroyMaterialSubcategory'])->name('master-data.material-subcategories.destroy');
        Route::post('master-data/material-vendors', [MasterDataController::class, 'storeMaterialVendor'])->name('master-data.material-vendors.store');
        Route::patch('master-data/material-vendors/{id}', [MasterDataController::class, 'updateMaterialVendor'])->name('master-data.material-vendors.update');
        Route::delete('master-data/material-vendors/{id}', [MasterDataController::class, 'destroyMaterialVendor'])->name('master-data.material-vendors.destroy');

        // MasterPlan
        Route::post('masterplan/bulk-edit', [MasterPlanController::class, 'editBulk'])->name('masterplan.bulk-edit');
        Route::put('masterplan/bulk-update', [MasterPlanController::class, 'updateBulk'])->name('masterplan.bulk-update');
        Route::put('masterplan/inline-bulk-update', [MasterPlanController::class, 'updateInlineBulk'])->middleware('role:admin')->name('masterplan.inline-bulk-update');
        Route::resource('masterplan', MasterPlanController::class)->except(['show']);

        Route::get('ocs/export', [OCSController::class, 'export'])->name('ocs.export');

        // OCS
        Route::resource('ocs', OCSController::class)->except(['show']);
        Route::get('ocs/{id}/image', [OCSController::class, 'image'])->name('ocs.image');
        Route::patch('ocs/{id}/status', [OCSController::class, 'updateStatus'])->name('ocs.status');
        Route::get('ocs/{id}/material-requirements', [OCSController::class, 'materialRequirements'])->name('ocs.material-requirements');
        Route::get('ocs/{id}/material-requirements/export', [OCSController::class, 'exportMaterialRequirements'])->name('ocs.material-requirements.export');
        Route::post('ocs/import', [OCSController::class, 'import'])->name('ocs.import');

        Route::get('norm/materials', [NormController::class, 'materials'])->name('norm.materials');
        Route::get('development-norms', [DevelopmentNormController::class, 'index'])->name('development-norms.index');
        Route::get('development-norms/{cutsheetId}', [DevelopmentNormController::class, 'show'])->name('development-norms.show');
        Route::put('development-norms/{cutsheetId}', [DevelopmentNormController::class, 'update'])->name('development-norms.update');
        Route::delete('development-norms/{cutsheetId}/items', [DevelopmentNormController::class, 'destroyItems'])
            ->middleware('role:admin')->name('development-norms.items.destroy');
        Route::get('norm/materials/{id}/replacements', [\App\Http\Controllers\NormMaterialReplacementController::class, 'index'])->name('norm.replacements');
        Route::post('norm/materials/{id}/replacements', [\App\Http\Controllers\NormMaterialReplacementController::class, 'store'])->name('norm.replacements.store');
        Route::get('norm/materials/{id}/delivery-bills', [\App\Http\Controllers\DeliveryBillController::class, 'index'])->name('norm.delivery-bills');
        Route::post('norm/materials/{id}/delivery-bills', [\App\Http\Controllers\DeliveryBillController::class, 'store'])->name('norm.delivery-bills.store');
        Route::get('norm/materials/{id}/delivery-bills/{bill}/download', [\App\Http\Controllers\DeliveryBillController::class, 'download'])->name('norm.delivery-bills.download');
        Route::get('norm/materials/{id}/defects', [\App\Http\Controllers\NormMaterialDefectController::class, 'index'])->name('norm.defects');
        Route::post('norm/materials/{id}/defects', [\App\Http\Controllers\NormMaterialDefectController::class, 'store'])->name('norm.defects.store');
        Route::get('norm/materials/{id}/defects/{defect}/image', [\App\Http\Controllers\NormMaterialDefectController::class, 'image'])->name('norm.defects.image');
        Route::get('norm/materials/{id}/images', [NormController::class, 'materialImages'])->name('norm.materials.images');
        Route::get('norm/materials/export', [NormController::class, 'exportMaterials'])->name('norm.materials.export');
        Route::get('norm/materials/{id}', [NormController::class, 'materialDetail'])->name('norm.materials.show');
        Route::put('norm/materials/{id}/confirmed', [NormController::class, 'updateConfirmed'])->name('norm.materials.confirmed');

        Route::get('revenue/export', [RevenueController::class, 'export'])->name('revenue.export');

        // Revenue
        Route::resource('revenue', RevenueController::class)->except(['show']);

        Route::get('holidays/export', [HolidayController::class, 'export'])->name('holidays.export');

        // Holidays
        Route::resource('holidays', HolidayController::class);

        // Colors (Line master)
        Route::resource('colors', ColorController::class)->except(['show']);

        // BOM
        Route::get('bom/material-suggestions', [BOMController::class, 'materialSuggestions'])->name('bom.material-suggestions');
        Route::get('bom/export/{id}', [BOMController::class, 'export'])->name('bom.export');
        Route::post('bom/import-preview', [BOMController::class, 'importPreview'])->name('bom.import-preview');
        Route::post('bom/import-store', [BOMController::class, 'importStore'])->name('bom.import-store');
        Route::post('bom/{bom}/clone', [BOMController::class, 'clone'])->name('bom.clone');
        Route::post('bom/{bom}/colorways', [BOMController::class, 'saveColorways'])->name('bom.colorways.save');
        Route::resource('bom', BOMController::class);
        Route::get('bom/{id}/image', [BOMController::class, 'image'])->name('bom.image');

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
        Route::get('mrp/create-po/{id}', [MRPController::class, 'createPoFromMrp'])->middleware('ppic.team:create')->name('mrp.create-po');
        Route::resource('mrp', MRPController::class)->except(['store', 'edit', 'update']);

        // Procurement
        Route::post('procurement/{id}/excel', [\App\Http\Controllers\PurchaseOrderExcelController::class, 'export'])->middleware('ppic.team:view')->name('procurement.excel');
        Route::post('procurement/{id}/pdf', [\App\Http\Controllers\PurchaseOrderPdfController::class, 'export'])->middleware('ppic.team:view')->name('procurement.pdf');
        Route::get('procurement/suppliers', [ProcurementController::class, 'suppliers'])->middleware('role:admin')->name('procurement.suppliers');
        Route::post('procurement/suppliers', [ProcurementController::class, 'suppliersStore'])->middleware('role:admin')->name('procurement.suppliers.store');
        Route::patch('procurement/suppliers/{id}', [ProcurementController::class, 'supplierUpdate'])->middleware('role:admin')->name('procurement.suppliers.update');
        Route::get('procurement/create-from-mrp/{mrpId}', [ProcurementController::class, 'createFromMrp'])->middleware('ppic.team:create')->name('procurement.create-from-mrp');
        Route::post('procurement/store', [ProcurementController::class, 'store'])->middleware('ppic.team:create')->name('procurement.store');
        Route::post('procurement/from-suggestions', [ProcurementController::class, 'createFromSuggestions'])->middleware('ppic.team:create')->name('procurement.from-suggestions');
        Route::patch('procurement/{id}/status', [ProcurementController::class, 'updateStatus'])->middleware('role:admin')->name('procurement.status');
        Route::patch('procurement/{id}/close', [ProcurementController::class, 'close'])->middleware('role:admin')->name('procurement.close');
        Route::patch('procurement/{id}/eta', [ProcurementController::class, 'updateEta'])->middleware('role:admin')->name('procurement.eta.update');
        Route::post('procurement/{id}/receipts/import', [ProcurementController::class, 'importReceiptRows'])->middleware('role:admin')->name('procurement.receipts.import');
        Route::post('procurement/{id}/receipts', [ProcurementController::class, 'receive'])->middleware('role:admin')->name('procurement.receipts.store');
        Route::get('procurement/{procurement}/receipts/{receipt}', [ProcurementController::class, 'receiptHistory'])->middleware('ppic.team:view')->name('procurement.receipts.show');
        Route::patch('procurement/{procurement}/receipts/{receipt}', [ProcurementController::class, 'updateReceiptHistory'])->middleware('role:admin')->name('procurement.receipts.update');
        Route::get('procurement', [ProcurementController::class, 'index'])->middleware('ppic.team:view')->name('procurement.index');
        Route::get('procurement/create', [ProcurementController::class, 'create'])->middleware('ppic.team:create')->name('procurement.create');
        Route::get('procurement/{procurement}', [ProcurementController::class, 'show'])->middleware('ppic.team:view')->name('procurement.show');
        Route::get('procurement/{procurement}/edit', [ProcurementController::class, 'edit'])->middleware('role:admin')->name('procurement.edit');
        Route::put('procurement/{procurement}', [ProcurementController::class, 'update'])->middleware('role:admin')->name('procurement.update');
        Route::patch('procurement/{procurement}', [ProcurementController::class, 'update'])->middleware('role:admin');
        Route::delete('procurement/{procurement}', [ProcurementController::class, 'destroy'])->middleware('role:admin')->name('procurement.destroy');

        // Inventory
        Route::get('stock-records', [StockRecordController::class, 'index'])->name('stock-records.index');
        Route::post('stock-records/sync', [StockRecordController::class, 'sync'])->name('stock-records.sync');
        Route::get('stock-records/{id}', [StockRecordController::class, 'show'])->name('stock-records.show');
        Route::get('stock-records/{id}/transactions/{transactionId}/edit', [StockRecordController::class, 'editIssue'])->middleware('role:admin,warehouse')->name('stock-records.issues.edit');
        Route::put('stock-records/{id}/transactions/{transactionId}', [StockRecordController::class, 'updateIssue'])->middleware('role:admin,warehouse')->name('stock-records.issues.update');
        Route::delete('stock-records/{id}/transactions/{transactionId}', [StockRecordController::class, 'voidIssue'])->middleware('role:admin,warehouse')->name('stock-records.issues.void');
        Route::patch('stock-records/{id}/position', [StockRecordController::class, 'position'])->name('stock-records.position');
        Route::put('stock-records/{id}/priorities', [StockRecordController::class, 'priorities'])->name('stock-records.priorities');
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
        Route::get('inventory/code/{materialCode}', [InventoryController::class, 'codeDetails'])->name('inventory.code-details');
        Route::post('inventory/{id}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::patch('inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::delete('inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
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
