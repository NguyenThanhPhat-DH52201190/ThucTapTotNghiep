@extends('layouts.app')
@section('title', 'Order Cutsheet')
@section('content')
@php
$canManage = auth()->user()->role === 'admin';
@endphp

<form method="GET" action="{{ url()->current() }}" class="row g-3 mb-4">

    <div class="col-md-2">
    <label>CS</label>
    <input type="text" name="cs" class="form-control"
        placeholder="Fill CS"
        value="{{ request('cs') }}">
</div>

<div class="col-md-2">
    <label>Customer</label>
    <input type="text" name="customer" class="form-control"
        placeholder="Fill Customer"
        value="{{ request('customer') }}">
</div>

<div class="col-md-2">
    <label>SName</label>
    <input type="text" name="sname" class="form-control"
        placeholder="Fill SName"
        value="{{ request('sname') }}">
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

        @if($canManage)
        <a href="{{ route('admin.ocs.create') }}" class="btn btn-primary">
            Add
        </a>
        @endif

    </div>
</form>
<table class="table">
    <thead>
        <tr>
            <th scope="col">CS</th>
            <th scope="col">CsDate</th>
            <th scope="col">SNo</th>
            <th scope="col">SName</th>
            <th scope="col">Customer</th>
            <th scope="col">Color</th>
            <th scope="col">ONum</th>
            <th scope="col">CMT</th>
            <th scope="col">Qty</th>
            @if($canManage)
            <th scope="col">Edit</th>
            <th scope="col">Delete</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($orders as $item) 
        <tr>
            <td>{{ $item->CS }}</td>
            <td>{{ $item->CsDate }}</td>
            <td>{{ $item->SNo }}</td>
            <td>{{ $item->Sname }}</td>
            <td>{{ $item->Customer }}</td>
            <td>{{ $item->Color }}</td>
            <td>{{ $item->ONum }}</td>
            <td>{{ $item->CMT }}</td>
            <td>{{ $item->Qty }}</td>
            @if($canManage)
            <td>
                <a href="{{ route('admin.ocs.edit', $item->id) }}"
                    class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </td>
            <td>
                <form method="POST" action="{{ route('admin.ocs.destroy', $item->id) }}"
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