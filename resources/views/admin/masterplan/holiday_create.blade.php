@extends('layouts.app')

@section('content')
@if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<h3>Add Holiday</h3>

<form method="POST" action="{{ route('admin.holidays.store') }}">
    @csrf

    <div class="mb-3">
        <label>Date</label>
        <input type="date" name="holiday" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" class="form-control">
    </div>

    <button class="btn btn-primary">Add</button>
    <a href="{{ route('admin.holidays.index') }}" class="btn btn-secondary">Back</a>
</form>

@endsection