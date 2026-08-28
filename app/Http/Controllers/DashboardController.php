<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $role = $request->user()->role;

        $route = match ($role) {
            User::ROLE_ADMIN => 'admin.ocs.index',
            User::ROLE_PPIC => 'admin.masterplan.index',
            User::ROLE_IE => 'admin.bom.index',
            User::ROLE_WAREHOUSE => 'admin.inventory.index',
            User::ROLE_PROD => 'admin.shopfloor.dashboard',
            User::ROLE_ACCOUNTANT => 'admin.finance.dashboard',
            default => 'login',
        };

        return redirect()->route($route);
    }
}
