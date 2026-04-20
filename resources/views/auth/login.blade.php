@extends('layouts.guest')
@section('title', 'Login')
@section('heading', 'Login')
@section('subtitle', 'Sign in with your username and password.')


@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3">
        @csrf

        <div>
            <label for="username" class="form-label fw-semibold">Username</label>
            <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required autofocus>
        </div>

        <div>
            <label for="password" class="form-label fw-semibold">Password</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>

        <button class="btn btn-warning text-dark fw-semibold py-2" type="submit">Login</button>
    </form>

    <p class="text-center text-secondary mt-4 mb-0">
        No account yet?
        <a class="link-primary fw-semibold text-decoration-none" href="{{ route('register') }}">Create one</a>
    </p>

@endsection
