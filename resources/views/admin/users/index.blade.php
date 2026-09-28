@extends('layouts.app')

@section('title', 'Account Management')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->has('account'))
        <div class="alert alert-danger">{{ $errors->first('account') }}</div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Create account</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label" for="new-username">Username</label>
                    <input id="new-username" name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" required minlength="3" maxlength="50">
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="new-role">Role</label>
                    <select id="new-role" name="role" class="form-select @error('role') is-invalid @enderror" required data-team-toggle="new-team-wrap">
                        @foreach($roles as $role)<option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>@endforeach
                    </select>
                    @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3" id="new-team-wrap">
                    <label class="form-label" for="new-team">PPIC team</label>
                    <select id="new-team" name="ppic_team" class="form-select">
                        <option value="track" @selected(old('ppic_team') === 'track')>Track PO</option>
                        <option value="create" @selected(old('ppic_team') === 'create')>Create PO</option>
                        <option value="both" @selected(old('ppic_team', 'both') === 'both')>Both teams</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="new-password">Password</label>
                    <input id="new-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="new-password-confirmation">Confirm password</label>
                    <input id="new-password-confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-person-plus"></i> Create</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Accounts</h5></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Username</th><th>Role</th><th>PPIC team</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="fw-semibold">{{ $user->name }} @if($user->is(auth()->user()))<span class="badge bg-secondary">You</span>@endif</td>
                            <td><span class="badge text-bg-light border">{{ ucfirst($user->role) }}</span></td>
                            <td>{{ $user->role === 'ppic' ? match($user->ppic_team) { 'track' => 'Track PO', 'create' => 'Create PO', default => 'Both teams' } : '—' }}</td>
                            <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-user-{{ $user->id }}" aria-expanded="false">Edit / Reset password</button>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Delete account {{ $user->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" @disabled($user->is(auth()->user()))>Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="edit-user-{{ $user->id }}">
                            <td colspan="5" class="bg-light">
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="row g-3 align-items-end p-2">
                                    @csrf @method('PATCH')
                                    <div class="col-md-3"><label class="form-label">Username</label><input name="username" value="{{ $user->name }}" class="form-control" required minlength="3" maxlength="50"></div>
                                    <div class="col-md-2"><label class="form-label">Role</label><select name="role" class="form-select" required data-team-toggle="team-{{ $user->id }}">@foreach($roles as $role)<option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>@endforeach</select></div>
                                    <div class="col-md-2" id="team-{{ $user->id }}"><label class="form-label">PPIC team</label><select name="ppic_team" class="form-select"><option value="track" @selected($user->ppic_team === 'track')>Track PO</option><option value="create" @selected($user->ppic_team === 'create')>Create PO</option><option value="both" @selected(in_array($user->ppic_team, ['both', null], true))>Both teams</option></select></div>
                                    <div class="col-md-2"><label class="form-label">New password</label><input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep"></div>
                                    <div class="col-md-2"><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                                    <div class="col-md-2"><button class="btn btn-primary w-100">Save changes</button></div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $users->links() }}</div>
    </div>
    <p class="text-muted small mt-3"><i class="bi bi-shield-lock"></i> Passwords are stored securely as hashes. Existing passwords cannot be viewed; set a new password to reset one.</p>
</div>
<script>
document.querySelectorAll('[data-team-toggle]').forEach((roleSelect) => {
    const wrapper = document.getElementById(roleSelect.dataset.teamToggle);
    const teamSelect = wrapper?.querySelector('select');
    const sync = () => {
        const visible = roleSelect.value === 'ppic';
        if (wrapper) wrapper.hidden = !visible;
        if (teamSelect) teamSelect.disabled = !visible;
    };
    roleSelect.addEventListener('change', sync);
    sync();
});
</script>
@endsection
