@extends('layouts.app')
@section('title', 'Add Order Cutsheet')

@section('content')

<h3>Add Order Cutsheet</h3>

<form method="POST" action="{{ route('admin.ocs.store') }}">
    @csrf

    <div class="mb-3">
        <label>CS</label>
        <input type="text" name="CS" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>CsDate</label>
        <input type="date" name="CsDate" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>SNo</label>
        <input type="text" name="SNo" class="form-control">
    </div>

    <div class="mb-3">
        <label>SName</label>
        <input type="text" name="Sname" class="form-control">
    </div>

    <div class="mb-3">
        <label>Customer</label>
        <input type="text" name="Customer" class="form-control">
    </div>

    <div class="mb-3">
        <label>Color</label>
        <input type="text" name="Color" class="form-control">
    </div>

    <div class="mb-3">
        <label>ONum</label>
        <input type="text" name="ONum" class="form-control">
    </div>

    <div class="mb-3">
        <label>CMT</label>
        <input type="number" name="CMT" step="0.01" class="form-control">
    </div>

    <div class="mb-3">
        <label>Qty</label>
        <input type="number" name="Qty" class="form-control">
    </div>

    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('admin.ocs.index') }}" class="btn btn-secondary">Back</a>
</form>

@endsection