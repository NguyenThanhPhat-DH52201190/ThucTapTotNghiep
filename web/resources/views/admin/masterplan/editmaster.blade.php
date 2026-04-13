@extends('layouts.app')
@section('title', 'Edit Master Plan')

@section('content')


<h3>Edit Master Plan</h3>
@error('Qty_dis')
<div class="text-danger">{{ $message }}</div>
@enderror
<form method="POST" action="{{ route('admin.masterplan.update', $plan->id) }}">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label>CU</label>
        <input type="text" name="CU" class="form-control"
            value="{{ $plan->CU }}" readonly>
    </div>

    <div class="mb-3">
        <label>Line</label>
        <input type="text" name="Line" class="form-control"
            value="{{ $plan->Line }}">
    </div>

    <div class="mb-3">
        <label>Rdate</label>
        <input type="date" name="Rdate" class="form-control"
            value="{{ $plan ->Rdate }}"
            min="{{ date('Y-m-d') }}">
    </div>
    <div class="mb-3">
        <label>ETADate</label>
        <input type="date" name="ETADate" class="form-control"
            value="{{ $plan->ETADate }}"
            min="{{ date('Y-m-d') }}">
    </div>
    <div class="mb-3">
        <label>ActDate</label>
        <input type="date" name="ActDate" class="form-control"
            value="{{ $plan->ActDate }}"
            min="{{ date('Y-m-d') }}">
    </div>

    <div class="mb-3">
        <label>PO</label>
        <input type="text" class="form-control" value="{{ $plan->PO }}" readonly>
    </div>

    <div class="mb-3">
        <label>LT</label>
        <input type="number" name="lt" class="form-control" min="0" value="{{ $plan->lt }}">
    </div>

    <div class="mb-3">
        <label>FirstOPT</label>
        <input type="date" name="FirstOPT" class="form-control" min="0" value="{{ \Carbon\Carbon::parse($plan->FirstOPT)->format('Y-m-d') }}"">
    </div>

    <div class="mb-3">
        <label>Finish_SEW</label>
        <input type="text" id="finishSew" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label>EX_Fact</label>
        <input type="text" id="exFact" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label>Qty_dis</label>
        <input type="number" name="Qty_dis" class="form-control" min="0" value="{{ $plan->Qty_dis }}">
    </div>

    <div class="mb-3">
        <label>Style</label>
        <input type="text" class="form-control" value="{{ $plan->Style }}" readonly>
    </div>

    <button class="btn btn-primary">Update</button>

    <a href="{{ route('admin.masterplan.index') }}" class="btn btn-secondary">
        Back
    </a>

</form>

@endsection