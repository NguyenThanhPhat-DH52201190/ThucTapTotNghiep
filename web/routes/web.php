<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterPlanController;
use App\Http\Controllers\OCSController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\HolidayController;
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
        ->middleware('role:admin,ppic')
        ->name('ordercutsheet.view');

    Route::get('/order-cutsheet/export', [OCSController::class, 'export'])
        ->middleware('role:admin,ppic')
        ->name('ordercutsheet.export');

    Route::get('/master-plan', [MasterPlanController::class, 'index'])
        ->middleware('role:admin,ie,warehouse')
        ->name('masterplan.view');

    Route::get('/master-plan/export', [MasterPlanController::class, 'export'])
        ->middleware('role:admin,ie,warehouse')
        ->name('masterplan.export');

    Route::get('/revenue-view', [RevenueController::class, 'index'])
        ->middleware('role:admin,ie,warehouse')
        ->name('revenue.view');

    Route::get('/revenue-view/export', [RevenueController::class, 'export'])
        ->middleware('role:admin,ie,warehouse')
        ->name('revenue.export');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

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

// routes/web.php
