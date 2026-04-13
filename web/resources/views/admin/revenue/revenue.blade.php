@extends('layouts.app')
@section('title', 'Revenue ')
@section('content')
@php
$canManage = auth()->user()->role === 'admin';
@endphp

@if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif
<form method="GET" action="{{ url()->current() }}" class="row g-3 mb-4">

    <div class="col-md-2">
    <label>CS</label>
    <input type="text" name="cs" class="form-control"
        placeholder="Fill CS"
        value="{{ request('cs') }}">
</div>

    <div class="col-md-4 d-flex align-items-end gap-2">

        <!-- SEARCH -->
        <button type="submit" class="btn btn-dark">
            Search
        </button>

        <a href="{{ request()->url() }}"
            class="btn btn-outline-secondary">
            Reset
        </a>

        <a href="{{ route('revenue.export', request()->query()) }}"
            class="btn btn-success">
            Export Excel
        </a>

        @if($canManage)
        <a href="{{ route('admin.revenue.create') }}" class="btn btn-primary">
            Add
        </a>
        @endif

    </div>
</form>
<table class="table">
    <thead>
        <tr>
            <th scope="col">CS</th>
            <th scope="col">planout</th>
            <th scope="col">actualout</th>
            <th scope="col">sewingmp</th>
            <th scope="col">workhrs</th>
            <th scope="col">cmp</th>
            @if($canManage)
            <th scope="col">Edit</th>
            <th scope="col">Delete</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($revenues as $item) 
        <tr>
            <td>{{ $item->CS }}</td>
            <td>{{ $item->planout }}</td>
            <td>{{ $item->actualout }}</td>
            <td>{{ $item->sewingmp }}</td>
            <td>{{ $item->workhrs }}</td>
            <td>{{ $item->cmp }}</td>
            @if($canManage)
            <td>
                <a href="{{ route('admin.revenue.edit', $item->id) }}"
                    class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </td>
            <td>
                <form method="POST" action="{{ route('admin.revenue.destroy', $item->id) }}"
                    onsubmit="return confirm('Are you sure?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
