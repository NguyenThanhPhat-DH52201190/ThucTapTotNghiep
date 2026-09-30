@extends('layouts.app')
@section('title', 'Inventory details - '.$materialCode)
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @php
        $currentTotal = (float) $items->sum('current_qty');
        $reservedTotal = (float) $items->sum('reserved_qty');
        $availableTotal = (float) $items->sum('available_qty');
        $valueTotal = (float) $items->sum(fn ($item) => (float) $item->current_qty * (float) $item->unit_cost);
    @endphp
    <div class="card shadow-sm border-0 mb-4"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div><h4 class="fw-bold mb-1">Inventory details: <code>{{ $materialCode }}</code></h4><div class="text-muted">{{ $items->count() }} stock balance records for this code</div></div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.inventory.index') }}"><i class="bi bi-arrow-left me-1"></i>Back to Inventory</a>
    </div></div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted">Current</div><strong class="fs-4">{{ number_format($currentTotal, 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted">Reserved</div><strong class="fs-4">{{ number_format($reservedTotal, 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted">Available</div><strong class="fs-4">{{ number_format($availableTotal, 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted">Total value</div><strong class="fs-4">$ {{ number_format($valueTotal, 4) }}</strong></div></div></div>
    </div>
    <div class="card border-0 shadow-sm"><div class="card-header bg-white"><h5 class="mb-0">Stock balances under {{ $materialCode }}</h5></div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" style="min-width:1500px">
            <thead class="table-light"><tr><th>Old Code</th><th>Name</th><th>Type</th><th>Warehouse / Location</th><th>Custom Code</th><th>Lot / Roll</th><th class="text-end">Current</th><th class="text-end">Reserved</th><th class="text-end">Available</th><th class="text-end">Min Stock</th><th class="text-end">Reorder Point</th><th class="text-end">Unit Cost</th><th class="text-end">Value</th>@if($canManage)<th>Actions</th>@endif</tr></thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td><code>{{ $item->old_code ?: '-' }}</code></td><td>{{ $item->material_name }}</td><td><span class="badge bg-info">{{ ucfirst($item->material_type ?: 'other') }}</span></td>
                        <td>{{ $item->warehouse_code ?: '-' }} / {{ $item->location_code ?: ($item->location_bin ?: '-') }}</td><td>{{ $item->custom_code ?: '-' }}</td><td>{{ $item->batch_no ?: '-' }}</td>
                        <td class="text-end text-nowrap">{{ number_format($item->current_qty, 0) }} {{ $item->unit }}</td><td class="text-end text-nowrap">{{ number_format($item->reserved_qty, 0) }}</td><td class="text-end text-nowrap fw-bold">{{ number_format($item->available_qty, 0) }}</td>
                        <td class="text-end">{{ number_format($item->min_stock_level, 0) }}</td><td class="text-end">{{ number_format($item->reorder_point, 2) }}</td><td class="text-end">{{ number_format($item->unit_cost, 4) }}</td><td class="text-end fw-bold text-nowrap">$ {{ number_format($item->current_qty * $item->unit_cost, 4) }}</td>
                        @if($canManage)<td class="text-nowrap"><button class="btn btn-sm btn-warning" onclick='editInventory(@json($item))' aria-label="Edit balance"><i class="bi bi-pencil"></i></button> <form method="POST" action="{{ route('admin.inventory.destroy', $item->id) }}" class="d-inline" onsubmit="return confirm('Delete this inventory balance?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" aria-label="Delete balance"><i class="bi bi-trash"></i></button></form></td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
</div>

@if($canManage)
<div class="modal fade" id="adjustModal"><div class="modal-dialog"><form method="POST" id="adjustForm" class="modal-content">@csrf @method('PATCH')
    <div class="modal-header"><h6 class="modal-title">Adjust inventory balance</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p><strong id="adjustCode"></strong> · Current: <strong id="adjustCurrent"></strong></p><input type="hidden" id="adjustMaterialId" name="material_id">
        <div class="mb-3"><label class="form-label">Old Code</label><input id="adjustOldCode" name="old_code" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Custom Code</label><input id="adjustCustomCode" name="custom_code" class="form-control" maxlength="191"></div>
        <div class="mb-3"><label class="form-label">New Quantity</label><input id="adjustQty" type="number" step="0.0001" min="0" name="new_qty" class="form-control" required></div>
        <div class="row g-3 mb-3"><div class="col-6"><label class="form-label">Min Stock</label><input id="adjustMin" type="number" step="0.0001" min="0" name="min_stock_level" class="form-control" required></div><div class="col-6"><label class="form-label">Reorder Point</label><input id="adjustReorder" type="number" step="0.0001" min="0" name="reorder_point" class="form-control" required></div></div>
        <div><label class="form-label">Reason <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="2" required></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>
<script>
function editInventory(item) {
    document.getElementById('adjustCode').textContent = item.material_code;
    document.getElementById('adjustCurrent').textContent = item.current_qty;
    document.getElementById('adjustMaterialId').value = item.material_id;
    document.getElementById('adjustOldCode').value = item.old_code || '';
    document.getElementById('adjustCustomCode').value = item.custom_code || '';
    document.getElementById('adjustQty').value = item.current_qty;
    document.getElementById('adjustMin').value = item.min_stock_level;
    document.getElementById('adjustReorder').value = item.reorder_point;
    document.getElementById('adjustForm').action = '{{ url("admin/inventory") }}/' + item.id;
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
}
</script>
@endif
@endsection
