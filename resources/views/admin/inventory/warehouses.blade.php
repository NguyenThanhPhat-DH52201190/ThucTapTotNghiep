@extends('layouts.app')
@section('title', 'Warehouses')
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Warehouses</h5>
            @if($canManage)
                <div class="d-flex gap-2"><button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLocationModal"><i class="bi bi-grid-3x3-gap"></i> Add location</button><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add warehouse</button></div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Name</th><th>Location</th><th>Type</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($warehouses as $w)
                        <tr>
                            <td class="fw-bold">{{ $w->code }}</td>
                            <td>{{ $w->name }}</td>
                            <td><small>{{ $w->location ?? '-' }}</small></td>
                            <td><span class="badge bg-info">{{ str_replace('_', ' ', $w->type) }}</span></td>
                            <td><span class="badge bg-{{ $w->is_active ? 'success' : 'secondary' }}">{{ $w->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end">@if($canManage)<button class="btn btn-sm btn-warning" type="button" onclick='editWarehouse(@json($w))'><i class="bi bi-pencil"></i></button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-3 text-muted">No warehouses</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2"></i>Storage locations</h6></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Warehouse</th><th>Code</th><th>Name</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($locations as $location)<tr><td>{{ $location->warehouse_code }}</td><td class="fw-bold">{{ $location->location_code }}</td><td>{{ $location->location_name ?? '-' }}</td><td><span class="badge bg-{{ $location->is_active ? 'success' : 'secondary' }}">{{ $location->is_active ? 'Active' : 'Inactive' }}</span></td><td class="text-end">@if($canManage)<button type="button" class="btn btn-sm btn-warning" onclick='editLocation(@json($location))'><i class="bi bi-pencil"></i></button>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-center py-3 text-muted">No storage locations</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.inventory.warehouses.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Warehouse</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="raw_material">Raw Material</option>
                            <option value="wip">Work In Progress</option>
                            <option value="finished_goods">Finished Goods</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
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
@if($canManage)
<div class="modal fade" id="addLocationModal"><div class="modal-dialog"><form method="POST" action="{{ route('admin.inventory.locations.store') }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Add storage location</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="mb-3"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Location code</label><input name="location_code" class="form-control" required placeholder="e.g. A-01-02"></div><div class="mb-3"><label class="form-label">Location name</label><input name="location_name" class="form-control"></div></div>
    <div class="modal-footer"><button class="btn btn-primary">Save location</button></div>
</form></div></div>
@endif
@if($canManage)
<div class="modal fade" id="warehouseEditModal"><div class="modal-dialog"><form method="POST" id="warehouseEditForm" class="modal-content">@csrf @method('PATCH')<div class="modal-header"><h5 class="modal-title">Edit warehouse</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Code</label><input id="ewCode" name="code" class="form-control" required></div><div class="col-md-6"><label class="form-label">Name</label><input id="ewName" name="name" class="form-control" required></div><div class="col-12"><label class="form-label">Location</label><input id="ewLocation" name="location" class="form-control"></div><div class="col-md-6"><label class="form-label">Type</label><select id="ewType" name="type" class="form-select"><option value="raw_material">Raw Material</option><option value="wip">WIP</option><option value="finished_goods">Finished Goods</option></select></div><div class="col-md-6"><label class="form-label">Status</label><select id="ewActive" name="is_active" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div><div class="col-12"><label class="form-label">Notes</label><textarea id="ewNotes" name="notes" class="form-control"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div>
<div class="modal fade" id="locationEditModal"><div class="modal-dialog"><form method="POST" id="locationEditForm" class="modal-content">@csrf @method('PATCH')<div class="modal-header"><h5 class="modal-title">Edit storage location</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label">Warehouse</label><select id="elWarehouse" name="warehouse_id" class="form-select">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Location code</label><input id="elCode" name="location_code" class="form-control" required></div><div class="col-md-6"><label class="form-label">Name</label><input id="elName" name="location_name" class="form-control"></div><div class="col-12"><label class="form-label">Status</label><select id="elActive" name="is_active" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div></div></div><div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div>
@push('scripts')<script>function editWarehouse(w){document.getElementById('warehouseEditForm').action='{{ url('admin/inventory/warehouses') }}/'+w.id;for(const [id,key] of Object.entries({ewCode:'code',ewName:'name',ewLocation:'location',ewType:'type',ewActive:'is_active',ewNotes:'notes'})){document.getElementById(id).value=w[key]??'';}new bootstrap.Modal(document.getElementById('warehouseEditModal')).show();}function editLocation(l){document.getElementById('locationEditForm').action='{{ url('admin/inventory/locations') }}/'+l.id;document.getElementById('elWarehouse').value=l.warehouse_id;document.getElementById('elCode').value=l.location_code;document.getElementById('elName').value=l.location_name||'';document.getElementById('elActive').value=Number(l.is_active);new bootstrap.Modal(document.getElementById('locationEditModal')).show();}</script>@endpush
@endif
@endsection
