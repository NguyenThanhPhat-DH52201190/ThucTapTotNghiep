@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5">
            <p class="text-uppercase text-secondary small fw-semibold mb-2">Dashboard</p>
            <h1 class="h3 fw-bold mb-3">Welcome, {{ auth()->user()->name }}</h1>
            <p class="text-secondary mb-2">You are logged in successfully.</p>
            <p class="mb-4">Role: <span class="badge text-bg-warning text-dark text-uppercase">{{ auth()->user()->role }}</span></p>

            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Refresh Page</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-dark">Logout</button>
                </form>
            </div>
        </div>
    </section>
@endsection
