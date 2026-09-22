@extends('layouts.app')
@section('title', 'NORM - Materials')
@section('content')
@include('admin.partials.image-popover')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div><h5 class="mb-1 fw-bold"><i class="bi bi-rulers me-2"></i>NORM Materials — @include('admin.partials.image-trigger', ['imageUrl' => !empty($order->image_path) ? route('admin.ocs.image', $order->id, false) : null, 'imageLabel' => $order->CS])</h5><small class="text-muted">{{ $order->SNo }} — {{ $order->Sname }} · Qty {{ number_format($order->Qty, 0) }}</small></div>
            <div class="d-flex gap-2"><a href="{{ route('admin.norm.materials') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>CS List</a><a href="{{ route('admin.norm.materials.export', ['cutsheet_id' => $order->id]) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a></div>
        </div>
    </div>

    <div class="mb-3"><span class="text-muted">BOM:</span> @include('admin.partials.image-trigger', ['imageUrl' => $order->bom_image_id ? route('admin.bom.image', $order->bom_image_id, false) : null, 'imageLabel' => $order->bom_style . ' / ' . $order->bom_version])</div>
    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>CS</th><th>Style</th><th>Material</th><th>Description</th><th>Type</th><th>Colour / Size</th><th>Unit</th><th class="text-end">Product Qty</th><th class="text-end">Yield</th><th class="text-end">Waste %</th><th class="text-end">Required</th><th class="text-end">Available</th><th class="text-end">Shortage</th><th>Status</th></tr></thead>
        <tbody>@forelse($rows as $row)<tr>
            <td class="fw-semibold">@include('admin.partials.image-trigger', ['imageUrl' => !empty($order->image_path) ? route('admin.ocs.image', $order->id, false) : null, 'imageLabel' => $order->CS])</td><td>{{ $row->SNo }}</td><td><code>@include('admin.partials.material-image-trigger', ['imageLabel' => $row->material_code])</code></td><td>@include('admin.partials.material-image-trigger', ['imageLabel' => $row->material_name])</td><td><span class="badge bg-info">{{ ucfirst($row->material_type) }}</span></td>
            <td>{{ $row->material_color ?: '-' }} / {{ $row->material_size ?: '-' }}</td><td>{{ $row->unit }}</td><td class="text-end">{{ number_format($row->product_qty, 0) }}</td><td class="text-end">{{ number_format($row->consumption_rate, 4) }}</td><td class="text-end">{{ number_format($row->waste_percent, 2) }}</td>
            <td class="text-end fw-bold">{{ number_format($row->required_qty, 0) }}</td><td class="text-end">{{ number_format($row->available_qty, 0) }}</td><td class="text-end fw-bold {{ $row->shortage_qty > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($row->shortage_qty, 0) }}</td><td><span class="badge bg-{{ $row->stock_status === 'shortage' ? 'danger' : 'success' }}">{{ ucfirst($row->stock_status) }}</span></td>
        </tr>@empty<tr><td colspan="14" class="text-center text-muted py-4">No material requirements found.</td></tr>@endforelse</tbody>
    </table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div>
@endsection
