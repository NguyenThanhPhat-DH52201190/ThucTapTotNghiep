@extends('layouts.app')
@section('title', 'Stock Records')
@section('content')
<div class="card shadow-sm border-0 mb-4"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div><h4 class="fw-bold mb-1">Stock Records</h4><div class="text-muted">Opening stock, inventory history and material allocation by order. Quantities include all warehouses and lots.</div></div>
    <form method="POST" action="{{ route('admin.stock-records.sync') }}">@csrf<button class="btn btn-primary">Add new codes from Inventory</button></form>
</div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form class="d-flex gap-2 mb-3" method="GET"><input name="q" value="{{ request('q') }}" class="form-control" style="max-width:420px" placeholder="Material code or name"><button class="btn btn-dark">Search</button><a href="{{ route('admin.stock-records.index') }}" class="btn btn-outline-secondary">Reset</a></form>
<p class="small text-muted">Lower priority numbers appear first. Adding new codes takes a one-time opening snapshot; existing opening quantities are never reset.</p>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr><th>Priority</th><th>Material code</th><th>Name</th><th>Color / Size</th><th>Unit</th><th class="text-end">Opening</th><th class="text-end">On hand</th><th class="text-end">Reserved</th><th class="text-end">Available</th><th></th></tr></thead>
<tbody>@forelse($records as $record)<tr>
<td><form method="POST" action="{{ route('admin.stock-records.position', $record->id) }}" class="d-flex gap-1">@csrf @method('PATCH')<input aria-label="Priority for {{ $record->internal_code }}" name="sort_order" value="{{ $record->sort_order }}" type="number" min="1" max="1000000" required class="form-control form-control-sm" style="width:90px"><button class="btn btn-sm btn-outline-primary" title="Save priority">Save</button></form></td>
<td><a class="fw-semibold" href="{{ route('admin.stock-records.show', $record->id) }}">{{ $record->internal_code }}</a></td><td>{{ $record->material_name }}</td><td>{{ $record->color ?: '-' }} / {{ $record->size ?: '-' }}</td><td>{{ $record->unit }}</td>
<td class="text-end">{{ number_format($record->opening_qty, 0) }}</td><td class="text-end fw-bold">{{ number_format($record->on_hand, 0) }}</td><td class="text-end">{{ number_format($record->reserved, 0) }}</td><td class="text-end">{{ number_format(max(0, $record->on_hand - $record->reserved), 0) }}</td>
<td><a href="{{ route('admin.stock-records.show', $record->id) }}" class="btn btn-sm btn-primary text-nowrap">History &amp; planning</a></td>
</tr>@empty<tr><td colspan="10" class="text-center text-muted py-4">No stock records found. Use “Add new codes from Inventory” to begin tracking.</td></tr>@endforelse</tbody>
</table></div><div class="card-footer">{{ $records->links() }}</div></div>
@endsection
