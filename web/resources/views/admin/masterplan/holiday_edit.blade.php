@extends('layouts.app')

@section('content')

<h3>Edit Holiday</h3>

<form method="POST" action="{{ route('admin.holidays.update', $holiday->id) }}">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label>Date</label>
        <input type="date" name="holiday" class="form-control"
            value="{{ $holiday->holiday }}" required>
    </div>

    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" class="form-control"
            value="{{ $holiday->name }}">
    </div>

    <button class="btn btn-primary">Update</button>
    <a href="{{ route('admin.holidays.index') }}" class="btn btn-secondary">Back</a>
</form>

@endsection