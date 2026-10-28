<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    private function routeByRole(string $role): string
    {
        return match ($role) {
            User::ROLE_ADMIN => 'admin.ocs.index',
            User::ROLE_DEVELOPMENT => 'admin.development-norms.index',
            User::ROLE_PPIC => 'masterplan.view',
            User::ROLE_QA_QC => 'masterplan.view',
            User::ROLE_IE, User::ROLE_WAREHOUSE, User::ROLE_PROD, User::ROLE_ACCOUNTANT => 'masterplan.view',
            default => 'dashboard',
        };
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'name' => $request->string('username')->value(),
            'password' => $request->string('password')->value(),
        ];

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'username' => 'Invalid username or password.',
            ])->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->route($this->routeByRole(Auth::user()->role));
    }

    public function dashboard(): View
    {
        return view('dashboard');
    }

    public function adminDashboard(): View
    {
        return view('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
