@extends('layouts.app')
@section('title', 'Edit Order Cutsheet')

@section('content')

<h3>Edit Order Cutsheet</h3>

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

<form method="POST" action="{{ route('admin.ocs.update', $order->id) }}">
    @csrf
    @method('PUT')

    <div class="row">

        <div class="col-md-4 mb-3">
            <label>CS</label>
            <input type="text" name="CS" class="form-control"
                value="{{ old('CS', $order->CS) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>CsDate</label>
            <input type="date" name="CsDate" class="form-control"
                value="{{ old('CsDate', $order->CsDate) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>SNo</label>
            <input type="text" name="SNo" class="form-control"
                value="{{ old('SNo', $order->SNo) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>SName</label>
            <input type="text" name="Sname" class="form-control"
                value="{{ old('Sname', $order->Sname) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>Customer</label>
            <input type="text" name="Customer" class="form-control"
                value="{{ old('Customer', $order->Customer) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>Color</label>
            <input type="text" name="Color" class="form-control"
                value="{{ old('Color', $order->Color) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>ONum</label>
            <input type="text" name="ONum" class="form-control"
                value="{{ old('ONum', $order->ONum) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>CMT</label>
            <input type="number" step="0.01" name="CMT" class="form-control"
                value="{{ old('CMT', $order->CMT) }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>Qty</label>
            <input type="number" name="Qty" class="form-control"
                value="{{ old('Qty', $order->Qty) }}" required>
        </div>

    </div>

    <button type="submit" class="btn btn-primary">Update</button>

    <a href="{{ route('admin.ocs.index') }}"
        class="btn btn-secondary">
        Back
    </a>

</form>

@endsection