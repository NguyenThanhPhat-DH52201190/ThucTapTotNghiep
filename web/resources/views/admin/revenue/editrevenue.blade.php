@extends('layouts.app')
@section('title', 'Edit Revenue')

@section('content')

<h3>Edit Revenue</h3>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.revenue.update', $revenue->id) }}">
    @csrf
    @method('PUT')

    <div class="row">

        <!-- CS (không cho sửa) -->
        <div class="col-md-4 mb-3">
            <label>CS</label>
            <input type="text" class="form-control"
                value="{{ $revenue->CS }}" readonly>
        </div>

        <div class="col-md-4 mb-3">
            <label>Plan Out</label>
            <input type="number" name="planout" class="form-control"
                value="{{ old('planout', $revenue->planout) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>Actual Out</label>
            <input type="number" name="actualout" class="form-control"
                value="{{ old('actualout', $revenue->actualout) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>Sewing MP</label>
            <input type="number" name="sewingmp" class="form-control"
                value="{{ old('sewingmp', $revenue->sewingmp) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>Work Hours</label>
            <input type="number" name="workhrs" class="form-control"
                value="{{ old('workhrs', $revenue->workhrs) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>CMP</label>
            <input type="number" step="any" class="form-control"
                value="{{ $revenue->cmp }}" readonly>
        </div>

    </div>

    <button type="submit" class="btn btn-primary">Update</button>

    <a href="{{ route('admin.revenue.index') }}"
        class="btn btn-secondary">
        Back
    </a>

</form>

@endsection