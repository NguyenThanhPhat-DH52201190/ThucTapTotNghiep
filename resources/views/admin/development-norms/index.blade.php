@extends('layouts.app')
@section('title', 'Development Norms')
@section('content')
<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-1"><i class="bi bi-rulers me-2"></i>Development Norms</h5>
            <small class="text-muted">Confirmed OCS and independent BOM norm copies. Editing this module never changes the source BOM.</small>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm border-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>OCS / CS</th><th>Style</th><th>Customer</th><th class="text-end">Product Qty</th><th>Status</th><th class="text-end">Materials</th><th>Copied At</th><th></th></tr></thead>
            <tbody>@forelse($orders as $order)
                <tr>
                    <td class="fw-semibold">{{ $order->cs }}</td><td>{{ $order->style_no }}{{ $order->style_name ? ' — '.$order->style_name : '' }}</td>
                    <td>{{ $order->customer ?: '-' }}</td><td class="text-end">{{ number_format($order->product_qty, 0) }}</td>
                    <td><span class="badge bg-{{ $order->current_status === 'confirmed' ? 'success' : 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $order->current_status ?? 'confirmed')) }}</span></td>
                    <td class="text-end">{{ $order->item_count }}</td><td>{{ \Carbon\Carbon::parse($order->copied_at)->format('d/m/Y H:i') }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('admin.development-norms.show', $order->cutsheet_id) }}">Enter norms</a></td>
                </tr>
            @empty<tr><td colspan="8" class="text-center text-muted py-4">No confirmed OCS copies yet.</td>@endforelse</tbody>
        </table>
    </div>@if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif</div>
</div>
@endsection
