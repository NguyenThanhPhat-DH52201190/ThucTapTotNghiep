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
            User::ROLE_PPIC => 'masterplan.view',
            User::ROLE_IE, User::ROLE_WAREHOUSE => 'masterplan.view',
            default => 'login',
        };

        return redirect()->route($route);
    }
}
