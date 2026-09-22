@extends('layouts.app')
@section('title', 'NORM - Materials')
@section('content')
@include('admin.partials.image-popover')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><h5 class="mb-1 fw-bold"><i class="bi bi-rulers me-2"></i>NORM Materials</h5><small class="text-muted">Select an Order Cut Sheet to view its material requirements.</small></div>
        <div class="card-body"><form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">CS</label><input name="cs" value="{{ request('cs') }}" class="form-control" placeholder="Enter CS code"></div>
            <div class="col-auto"><button class="btn btn-dark"><i class="bi bi-search me-1"></i>Search</button></div>
            <div class="col-auto"><a href="{{ route('admin.norm.materials') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form></div>
    </div>

    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>CS</th><th>PO (ONum)</th><th>Style</th><th>Style Name</th><th>Customer</th><th>Color</th><th class="text-end">Qty</th><th>BOM</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>@forelse($orders as $order)<tr>
            <td class="fw-bold">@include('admin.partials.image-trigger', ['imageUrl' => !empty($order->image_path) ? route('admin.ocs.image', $order->id, false) : null, 'imageLabel' => $order->CS])</td><td>{{ $order->ONum ?? '-' }}</td><td>{{ $order->SNo }}</td><td>{{ $order->Sname }}</td><td>{{ $order->Customer }}</td><td>{{ $order->Color }}</td><td class="text-end fw-semibold">{{ number_format($order->Qty, 0) }}</td>
            <td>@include('admin.partials.image-trigger', ['imageUrl' => $order->bom_image_id ? route('admin.bom.image', $order->bom_image_id, false) : null, 'imageLabel' => $order->bom_style . ' / ' . $order->bom_version])</td><td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></td>
            <td><a href="{{ route('admin.norm.materials.show', $order->id) }}" class="btn btn-sm btn-primary"><i class="bi bi-eye me-1"></i>View Materials</a></td>
        </tr>@empty<tr><td colspan="10" class="text-center text-muted py-4">No OCS with a BOM found.</td></tr>@endforelse</tbody>
    </table></div>@if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif</div>
</div>
@endsection
