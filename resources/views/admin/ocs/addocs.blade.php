@extends('layouts.app')
@section('title', 'Add Order Cutsheet')

@section('content')

<h3>Add Order Cutsheet</h3>

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.ocs.store') }}">
    @csrf

    <div class="mb-3">
        <label>CS</label>
        <input type="text" name="CS" class="form-control" value="{{ old('CS') }}" required>
    </div>

    <div class="mb-3">
        <label>CsDate</label>
        <input type="date" name="CsDate" class="form-control" value="{{ old('CsDate') }}" required>
    </div>

    <div class="mb-3">
        <label>SNo</label>
        <input type="text" name="SNo" class="form-control" value="{{ old('SNo') }}" required>
    </div>

    <div class="mb-3">
        <label>SName</label>
        <input type="text" name="Sname" class="form-control" value="{{ old('Sname') }}" required>
    </div>

    <div class="mb-3">
        <label>Customer</label>
        <input type="text" name="Customer" class="form-control" value="{{ old('Customer') }}" required>
    </div>

    <div class="mb-3">
        <label>Color</label>
        <input type="text" name="Color" class="form-control" value="{{ old('Color') }}" required>
    </div>

    <div class="mb-3">
        <label>ONum</label>
        <input type="text" name="ONum" class="form-control" value="{{ old('ONum') }}" required>
    </div>

    <div class="mb-3">
        <label>CMT</label>
        <input type="number" name="CMT" step="0.01" class="form-control" value="{{ old('CMT') }}">
    </div>

    <div class="mb-3">
        <label>Qty</label>
        <input type="number" name="Qty" class="form-control" value="{{ old('Qty') }}" required>
    </div>

    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('admin.ocs.index') }}" class="btn btn-secondary">Back</a>
</form>

@endsection