@extends('layouts.app')
@section('title', 'Inventory Report')
@section('content')

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <h5 class="fw-bold mb-4"><i class="bi bi-graph-up me-2"></i>Inventory Summary</h5>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        @foreach($summary as $s)
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title text-muted">{{ ucfirst($s->material_type) }}</h6>
                        <h3 class="mb-0">{{ number_format($s->total_available, 0) }}</h3>
                        <small class="text-muted">{{ $s->item_count }} items</small>
                        <hr class="my-2">
                        <small>Value: <strong>{{ number_format($s->total_value, 0) }} đ</strong></small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Low Stock Alert -->
    @if(count($lowStock) > 0)
        <div class="card shadow-sm border-0 border-danger mb-4">
            <div class="card-header bg-danger text-white py-2">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alert ({{ count($lowStock) }} items)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th><th>Name</th><th>Type</th><th>Warehouse</th>
                            <th class="text-end">Available</th><th class="text-end">Reorder Point</th><th class="text-end">Min Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lowStock as $item)
                            <tr>
                                <td><code>{{ $item->material_code }}</code></td>
                                <td><small>{{ $item->material_name }}</small></td>
                                <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                                <td>{{ $item->warehouse_name ?? '-' }}</td>
                                <td class="text-end text-danger fw-bold">{{ number_format($item->available_qty, 2) }}</td>
                                <td class="text-end">{{ number_format($item->reorder_point, 2) }}</td>
                                <td class="text-end">{{ number_format($item->min_stock_level, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> No low stock items.</div>
    @endif
</div>
@endsection
