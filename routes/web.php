<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterPlanController;
use App\Http\Controllers\OCSController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ColorController;
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
        ->middleware('role:admin,ie,warehouse,ppic')
        ->name('masterplan.view');

    Route::get('/master-plan/export', [MasterPlanController::class, 'export'])
        ->middleware('role:admin,ie,warehouse')
        ->name('masterplan.export');

    Route::get('/revenue-view', [RevenueController::class, 'index'])
        ->middleware('role:admin,ie')
        ->name('revenue.view');

    Route::get('/revenue-view/export', [RevenueController::class, 'export'])
        ->middleware('role:admin,ie')
        ->name('revenue.export');

    Route::get('/revenue/sewing-lines/{cs}', [RevenueController::class, 'getSewingLinesByCs'])
        ->middleware('role:admin,ie')
        ->name('revenue.sewing-lines');

    Route::get('/revenue/distribution', [RevenueController::class, 'getDistributionByCsAndLine'])
        ->middleware('role:admin,ie')
        ->name('revenue.distribution');

    Route::get('/revenue/daily', [RevenueController::class, 'dailyRevenue'])
        ->middleware('role:admin,ie')
        ->name('revenue.daily.line');

    Route::get('/revenue/monthly-report', [RevenueController::class, 'monthlyReport'])
        ->middleware('role:admin,ie')
        ->name('revenue.monthly-report');

    Route::post('/revenue/daily', [RevenueController::class, 'storeDailyRevenue'])
        ->middleware('role:admin')
        ->name('revenue.daily.store');

    Route::post('/revenue/daily/matrix', [RevenueController::class, 'storeDailyRevenueMatrix'])
        ->middleware('role:admin')
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
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {

        // Trang dashboard admin riêng (nếu cần)
        Route::get('/dashboard', [AuthController::class, 'adminDashboard'])->name('dashboard');

        // MasterPlan
        Route::resource('masterplan', MasterPlanController::class);

        Route::get('ocs/export', [OCSController::class, 'export'])->name('ocs.export');

        // OCS
        Route::resource('ocs', OCSController::class);

        Route::get('revenue/export', [RevenueController::class, 'export'])->name('revenue.export');

        // Revenue
        Route::resource('revenue', RevenueController::class);

        Route::get('holidays/export', [HolidayController::class, 'export'])->name('holidays.export');

        // Holidays
        Route::resource('holidays', HolidayController::class);

        // Colors (Line master)
        Route::resource('colors', ColorController::class)->except(['show']);
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

Route::post('/ocs/import', [OCSController::class, 'import'])->name('ocs.import');
