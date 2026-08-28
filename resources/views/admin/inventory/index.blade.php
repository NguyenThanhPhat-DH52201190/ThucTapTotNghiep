@extends('layouts.app')
@section('title', 'Inventory')
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-boxes me-2"></i>Inventory Items</h5>
            <div class="d-flex gap-2">
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
                        <th>Code</th><th>Name</th><th>Type</th><th>Location</th>
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
                            <td><small>{{ $item->material_name }}</small></td>
                            <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                            <td><small>{{ $item->location_bin ?: '-' }}</small></td>
                            <td class="text-end">{{ number_format($item->current_qty, 2) }}</td>
                            <td class="text-end">{{ number_format($item->reserved_qty, 2) }}</td>
                            <td class="text-end fw-bold {{ $item->available_qty <= $item->reorder_point && $item->reorder_point > 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($item->available_qty, 2) }}
                            </td>
                            <td class="text-end">{{ number_format($item->min_stock_level, 2) }}</td>
                            <td class="text-end">{{ number_format($item->reorder_point, 2) }}</td>
                            <td class="text-end">{{ number_format($item->unit_cost, 0) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->current_qty * $item->unit_cost, 0) }} đ</td>
                            <td>
                                @if($canManage)
                                    <button class="btn btn-sm btn-warning" onclick="adjust({{ $item->id }}, '{{ $item->material_code }}', {{ $item->current_qty }})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center py-3 text-muted">No inventory items</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $items->links() }}</div>
    </div>
</div>

<!-- Adjust Modal -->
<div class="modal fade" id="adjustModal">
    <div class="modal-dialog modal-sm">
        <form method="POST" id="adjustForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Adjust Inventory</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong id="adjustCode"></strong></p>
                    <p>Current: <span id="adjustCurrent" class="fw-bold"></span></p>
                    <div class="mb-3">
                        <label class="form-label">New Quantity</label>
                        <input type="number" step="0.01" name="new_qty" class="form-control" required min="0">
                    </div>
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
function adjust(id, code, current) {
    document.getElementById('adjustCode').textContent = code;
    document.getElementById('adjustCurrent').textContent = current;
    document.getElementById('adjustForm').action = '{{ url("admin/inventory") }}/' + id + '/adjust';
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
}
</script>
@endsection
