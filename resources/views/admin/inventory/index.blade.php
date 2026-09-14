@extends('layouts.app')
@section('title', 'Inventory')
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-boxes me-2"></i>Inventory Items</h5>
            <div class="d-flex gap-2">
                @if($canManage)<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#openingModal"><i class="bi bi-plus-lg"></i> Add Inventory</button>@endif
                <a href="{{ route('admin.inventory.warehouses') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-building"></i> Warehouses</a>
                <a href="{{ route('admin.inventory.requisitions') }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-box-arrow-up-right"></i> Requisitions &amp; Issue</a>
                <a href="{{ route('admin.inventory.transactions') }}" class="btn btn-outline-info btn-sm"><i class="bi bi-arrow-left-right"></i> Transactions</a>
                <a href="{{ route('admin.inventory.report') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-graph-up"></i> Report</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="material_code" class="form-control" placeholder="Search material code..." value="{{ request('material_code') }}">
                </div>
                <div class="col-md-2">
                    <select name="material_type" class="form-select">
                        <option value="">All Types</option>
                        @foreach(['fabric','lining','pocket','trim','thread','zipper','label','elastic','interlining','other'] as $t)
                            <option value="{{ $t }}" {{ request('material_type') == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="lowStock" {{ request('low_stock') ? 'checked' : '' }}>
                        <label class="form-check-label" for="lowStock">Low Stock Only</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Filter</button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th><th>Old Code</th><th>Name</th><th>Type</th><th>Location</th>
                        <th class="text-end">Current</th><th class="text-end">Reserved</th>
                        <th class="text-end">Available</th><th class="text-end">Min Stock</th>
                        <th class="text-end">Reorder Point</th><th class="text-end">Unit Cost</th>
                        <th class="text-end">Value</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="{{ $item->available_qty <= $item->reorder_point && $item->reorder_point > 0 ? 'table-danger' : '' }}">
                            <td><code>{{ $item->material_code }}</code></td>
                            <td><code>{{ $item->old_code ?: '-' }}</code></td>
                            <td><small>{{ $item->material_name }}</small></td>
                            <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                            <td><small>{{ $item->warehouse_code ?: '-' }} / {{ $item->location_code ?: ($item->location_bin ?: '-') }}</small></td>
                            <td class="text-end">{{ number_format($item->current_qty, 2) }}</td>
                            <td class="text-end">{{ number_format($item->reserved_qty, 2) }}</td>
                            <td class="text-end fw-bold {{ $item->available_qty <= $item->reorder_point && $item->reorder_point > 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($item->available_qty, 2) }}
                            </td>
                            <td class="text-end">{{ number_format($item->min_stock_level, 2) }}</td>
                            <td class="text-end">{{ number_format($item->reorder_point, 2) }}</td>
                            <td class="text-end">{{ number_format($item->unit_cost, 4) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->current_qty * $item->unit_cost, 4) }} đ</td>
                            <td>
                                @if($canManage)
                                    <button class="btn btn-sm btn-warning" onclick='editInventory(@json($item))'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.inventory.destroy', $item->id) }}" class="d-inline" onsubmit="return confirm('Delete this inventory balance?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="text-center py-3 text-muted">No inventory items</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $items->links() }}</div>
    </div>
</div>

<!-- Opening Balance Modal -->
<div class="modal fade" id="openingModal"><div class="modal-dialog modal-lg"><form method="POST" action="{{ route('admin.inventory.store') }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Add Opening Inventory</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Material *</label><select name="material_id" class="form-select" required><option value="">Select material</option>@foreach($materials as $material)<option value="{{ $material->id }}">{{ $material->internal_code }}{{ $material->old_code ? ' / '.$material->old_code : '' }} — {{ $material->material_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Warehouse *</label><select name="warehouse_id" id="openingWarehouse" class="form-select" required onchange="filterOpeningLocations()"><option value="">Select</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Location *</label><select name="location_id" id="openingLocation" class="form-select" required><option value="">Select</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}">{{ $location->location_code }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Opening Qty *</label><input type="number" step="0.0001" min="0" name="opening_qty" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Unit Cost *</label><input type="number" step="0.0001" min="0" name="unit_cost" class="form-control" value="0.0000" required></div>
        <div class="col-md-4"><label class="form-label">Lot/Roll No.</label><input name="lot_roll_no" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Min Stock</label><input type="number" step="0.0001" min="0" name="min_stock_level" class="form-control" value="0" required></div>
        <div class="col-md-6"><label class="form-label">Reorder Point</label><input type="number" step="0.0001" min="0" name="reorder_point" class="form-control" value="0" required></div>
    </div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Opening Balance</button></div>
</form></div></div>

<!-- Edit / Adjust Modal -->
<div class="modal fade" id="adjustModal">
    <div class="modal-dialog">
        <form method="POST" id="adjustForm">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Adjust Inventory</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong id="adjustCode"></strong></p>
                    <p>Current: <span id="adjustCurrent" class="fw-bold"></span></p>
                    <input type="hidden" id="adjustMaterialId" name="material_id">
                    <div class="mb-3"><label class="form-label">Old Code</label><input id="adjustOldCode" name="old_code" class="form-control"></div>
                    <div class="mb-3">
                        <label class="form-label">New Quantity</label>
                        <input id="adjustQty" type="number" step="0.0001" name="new_qty" class="form-control" required min="0">
                    </div>
                    <div class="row g-3 mb-3"><div class="col-6"><label class="form-label">Min Stock</label><input id="adjustMin" type="number" step="0.0001" min="0" name="min_stock_level" class="form-control" required></div><div class="col-6"><label class="form-label">Reorder Point</label><input id="adjustReorder" type="number" step="0.0001" min="0" name="reorder_point" class="form-control" required></div></div>
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editInventory(item) {
    document.getElementById('adjustCode').textContent = item.material_code;
    document.getElementById('adjustCurrent').textContent = item.current_qty;
    document.getElementById('adjustMaterialId').value = item.material_id;
    document.getElementById('adjustOldCode').value = item.old_code || '';
    document.getElementById('adjustQty').value = item.current_qty;
    document.getElementById('adjustMin').value = item.min_stock_level;
    document.getElementById('adjustReorder').value = item.reorder_point;
    document.getElementById('adjustForm').action = '{{ url("admin/inventory") }}/' + item.id;
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
}
function filterOpeningLocations(){const warehouse=document.getElementById('openingWarehouse').value,select=document.getElementById('openingLocation');[...select.options].forEach((option,index)=>{if(index)option.hidden=option.dataset.warehouse!==warehouse});if(select.selectedOptions[0]?.hidden)select.value=''}
filterOpeningLocations();
</script>
@endsection
