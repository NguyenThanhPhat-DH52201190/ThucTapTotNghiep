@extends('layouts.app')
@section('title', 'Edit Order Cutsheet')

@section('content')

<h3>Edit Order Cutsheet</h3>

<form method="POST" action="{{ route('admin.ocs.update', $order->id) }}">
    @csrf
    @method('PUT')

    <div class="row">

        <div class="col-md-4 mb-3">
            <label>CS</label>
            <input type="text" name="CS" class="form-control"
                value="{{ $order->CS }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>CsDate</label>
            <input type="date" name="CsDate" class="form-control"
                value="{{ $order->CsDate }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>SNo</label>
            <input type="text" name="SNo" class="form-control"
                value="{{ $order->SNo }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>SName</label>
            <input type="text" name="Sname" class="form-control"
                value="{{ $order->Sname }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>Customer</label>
            <input type="text" name="Customer" class="form-control"
                value="{{ $order->Customer }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>Color</label>
            <input type="text" name="Color" class="form-control"
                value="{{ $order->Color }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>ONum</label>
            <input type="text" name="ONum" class="form-control"
                value="{{ $order->ONum }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>CMT</label>
            <input type="number" step="0.01" name="CMT" class="form-control"
                value="{{ $order->CMT }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>Qty</label>
            <input type="number" name="Qty" class="form-control"
                value="{{ $order->Qty }}">
        </div>

    </div>

    <button type="submit" class="btn btn-primary">Update</button>

    <a href="{{ route('admin.ocs.index') }}"
        class="btn btn-secondary">
        Back
    </a>

</form>

@endsection