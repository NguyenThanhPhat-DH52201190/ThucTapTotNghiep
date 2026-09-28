<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const ROLES = [
        User::ROLE_ADMIN,
        User::ROLE_WAREHOUSE,
        User::ROLE_PPIC,
        User::ROLE_IE,
        User::ROLE_PROD,
        User::ROLE_ACCOUNTANT,
        User::ROLE_DEVELOPMENT,
    ];

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(25),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,name'],
            'role' => ['required', Rule::in(self::ROLES)],
            'ppic_team' => ['nullable', Rule::in(['track', 'create', 'both'])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $data['username'],
            'email' => Str::uuid().'@local.user',
            'role' => $data['role'],
            'ppic_team' => $data['role'] === User::ROLE_PPIC ? ($data['ppic_team'] ?? User::PPIC_TEAM_BOTH) : null,
            'password' => $data['password'],
        ]);

        return to_route('admin.users.index')->with('success', 'Account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', Rule::unique('users', 'name')->ignore($user->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'ppic_team' => ['nullable', Rule::in(['track', 'create', 'both'])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if ($user->is($request->user()) && $data['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator access.']);
        }

        DB::transaction(function () use ($user, $data): void {
            $this->ensureAdminRemains($user, $data['role']);
            $user->name = $data['username'];
            $user->role = $data['role'];
            $user->ppic_team = $data['role'] === User::ROLE_PPIC ? ($data['ppic_team'] ?? User::PPIC_TEAM_BOTH) : null;
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
        });

        return to_route('admin.users.index')->with('success', 'Account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['account' => 'You cannot delete the account you are currently using.']);
        }

        DB::transaction(function () use ($user): void {
            $this->ensureAdminRemains($user, null);
            $user->delete();
        });

        return to_route('admin.users.index')->with('success', 'Account deleted.');
    }

    private function ensureAdminRemains(User $user, ?string $newRole): void
    {
        if ($user->role === User::ROLE_ADMIN && $newRole !== User::ROLE_ADMIN
            && User::where('role', User::ROLE_ADMIN)->where('id', '<>', $user->id)->lockForUpdate()->count() === 0) {
            abort(422, 'At least one administrator account must remain.');
        }
    }
}
