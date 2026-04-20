@extends('layouts.guest')

@section('title', 'Register')
@section('heading', 'Register')
@section('subtitle', 'Create an account with username and password only.')

@section('content')
<form method="POST" action="{{ route('register.store') }}" class="d-grid gap-3">
    @csrf

    <div>
        <label for="username" class="form-label fw-semibold">Username</label>
        <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required autofocus>
    </div>

    <div>
        <label for="role" class="form-label fw-semibold">Role</label>
        <select id="role" name="role" class="form-select" required>
            <option value="" disabled {{ old('role') ? '' : 'selected' }}>Select role</option>
            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
            <option value="ie" {{ old('role', 'ie') === 'ie' ? 'selected' : '' }}>IE</option>
            <option value="warehouse" {{ old('role') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
            <option value="ppic" {{ old('role') === 'ppic' ? 'selected' : '' }}>PPIC</option>
        </select>
    </div>
    
    <div>
        <label for="password" class="form-label fw-semibold">Password</label>
        <input type="password" id="password" name="password" class="form-control" required>
    </div>

    <div>
        <label for="password_confirmation" class="form-label fw-semibold">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
    </div>

    <button class="btn btn-warning py-2" type="submit">Create Account</button>
</form>

<p class="text-center text-secondary mt-4 mb-0">
    Already have an account?
    <a class="link-primary fw-semibold text-decoration-none" href="{{ route('login') }}">Back to Login</a>
</p>
@endsection