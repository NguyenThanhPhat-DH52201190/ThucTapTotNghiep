<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    private function routeByRole(string $role): string
    {
        return match ($role) {
            User::ROLE_ADMIN => 'admin.ocs.index',
            User::ROLE_PPIC => 'ordercutsheet.view',
            User::ROLE_IE, User::ROLE_WAREHOUSE => 'masterplan.view',
            default => 'dashboard',
        };
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,name'],
            'role' => [
                'required',
                Rule::in([User::ROLE_ADMIN, User::ROLE_IE, User::ROLE_WAREHOUSE, User::ROLE_PPIC]),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->exists()) {
                        $fail('An admin account already exists. You cannot register another admin role.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $user = User::create([
                'name' => $validated['username'],
                'email' => Str::uuid().'@local.user',
                'password' => $validated['password'],
                'role' => $validated['role'],
            ]);

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route($this->routeByRole($user->role));
        } catch (\Throwable $e) {
            Log::error('Failed to register user', [
                'message' => $e->getMessage(),
                'input' => $request->except(['_token', 'password', 'password_confirmation']),
            ]);

            return back()
                ->withInput($request->only('username', 'role'))
                ->withErrors([
                    'username' => 'Unable to create the account. Please try again.',
                ]);
        }
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