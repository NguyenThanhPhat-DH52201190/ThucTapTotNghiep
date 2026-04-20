@extends('layouts.app')
@section('title', 'Line Colors')

@section('content')
<h3>Line Colors</h3>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="d-flex gap-2 mb-3">
    <a href="{{ route('admin.colors.create') }}" class="btn btn-primary">Add Color</a>
    <a href="{{ route('admin.masterplan.index') }}" class="btn btn-secondary">Back to Master Plan</a>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Hex</th>
                <th>Category</th>
                <th>Preview</th>
                <th>Status</th>
                <th width="180">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($colors as $color)
            <tr>
                <td>{{ $color->name }}</td>
                <td>{{ $color->hex_code }}</td>
                <td>{{ $color->cate ?? 'GSV' }}</td>
                <td>
                    <span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:{{ $color->hex_code }};border:1px solid #cbd5e1;"></span>
                </td>
                <td>
                    @if($color->is_active)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                </td>
                <td class="d-flex gap-2">
                    <a href="{{ route('admin.colors.edit', $color->id) }}" class="btn btn-warning btn-sm">Edit</a>
                    <form method="POST" action="{{ route('admin.colors.destroy', $color->id) }}" onsubmit="return confirm('Delete this color?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">No color records</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
