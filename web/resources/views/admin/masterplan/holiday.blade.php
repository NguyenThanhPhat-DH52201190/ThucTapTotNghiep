@extends('layouts.app')

@section('content')

<h3>Holiday List</h3>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="d-flex gap-2 mb-3">
    <a href="{{ route('admin.holidays.create') }}" class="btn btn-success">Add Holiday</a>
    <a href="{{ route('admin.holidays.export') }}" class="btn btn-outline-success">Export Excel</a>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Name</th>
            <th width="150">Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach($holidays as $h)
        <tr>
            <td>{{ $h->holiday }}</td>
            <td>{{ $h->name }}</td>
            <td>
                <a href="{{ route('admin.holidays.edit', $h->id) }}" class="btn btn-warning btn-sm">Edit</a>

                <form action="{{ route('admin.holidays.destroy', $h->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button onclick="return confirm('Delete?')" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@endsection