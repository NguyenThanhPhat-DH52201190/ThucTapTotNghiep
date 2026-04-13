@extends('layouts.app')

@section('content')

<h3>Holiday List</h3>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
<a href="{{ route('admin.holidays.create') }}" class="btn btn-success mb-3">Add Holiday</a>

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