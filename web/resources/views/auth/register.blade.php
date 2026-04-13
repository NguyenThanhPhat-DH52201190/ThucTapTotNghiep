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
    <select name="role">
        <option value="admin">Admin</option>
        <option value="iec">IEC</option>
        <option value="warehouse">Warehouse</option>
        <option value="ppic">PPIC</option>
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