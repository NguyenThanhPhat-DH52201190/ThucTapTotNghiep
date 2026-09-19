@extends('layouts.app')
@section('title', 'Material Requirements - ' . $order->CS)
@section('content')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-boxes me-2"></i>Material Requirements — {{ $order->CS }}</h5>
            <a href="{{ route('admin.ocs.material-requirements.export', $order->id) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><span class="text-muted">Style</span><div class="fw-semibold">{{ $order->SNo }} — {{ $order->Sname }}</div></div>
                <div class="col-md-2"><span class="text-muted">Order Qty</span><div class="fw-semibold">{{ number_format($order->Qty) }}</div></div>
                <div class="col-md-2"><span class="text-muted">Color</span><div class="fw-semibold">{{ $order->Color }}</div></div>
                <div class="col-md-3"><span class="text-muted">BOM</span><div class="fw-semibold">{{ $bom->style_no }} / {{ $bom->version }}</div></div>
                <div class="col-md-2"><span class="text-muted">Status</span><div><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></div></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Code</th><th>Description</th><th>Type</th><th>Colour / Size</th><th>Unit</th>
                        <th class="text-end">Product Qty</th><th class="text-end">Yield</th><th class="text-end">Waste %</th>
                        <th class="text-end">Required</th><th class="text-end">On Hand</th><th class="text-end">Reserved</th>
                        <th class="text-end">Available</th><th class="text-end">Shortage</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requirements as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td><td><code>{{ $row->material_code }}</code></td><td>{{ $row->material_name }}</td>
                            <td><span class="badge bg-info">{{ ucfirst($row->material_type) }}</span></td>
                            <td>{{ $row->material_color ?: '-' }} / {{ $row->material_size ?: '-' }}</td><td>{{ $row->unit }}</td>
                            <td class="text-end">{{ number_format($row->product_qty, 0) }}</td>
                            <td class="text-end">{{ number_format($row->consumption_rate, 4) }}</td>
                            <td class="text-end">{{ number_format($row->waste_percent, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->required_qty, 0) }}</td>
                            <td class="text-end">{{ number_format($row->on_hand_qty, 0) }}</td>
                            <td class="text-end">{{ number_format($row->reserved_qty, 0) }}</td>
                            <td class="text-end">{{ number_format($row->available_qty, 0) }}</td>
                            <td class="text-end fw-bold {{ $row->shortage_qty > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($row->shortage_qty, 0) }}</td>
                            <td>@if($row->shortage_qty > 0)<span class="badge bg-danger">Shortage</span>@else<span class="badge bg-success">Sufficient</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="15" class="text-center text-muted py-4">No BOM material items.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
