@extends('layouts.app')
@section('title', 'MRP - ' . $mrp->mrp_code)
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <!-- Header -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-calculator me-2"></i>{{ $mrp->mrp_code }}
                @php $sc = match($mrp->status) { 'calculated'=>'success', 'approved'=>'primary', default=>'secondary' } @endphp
                <span class="badge bg-{{ $sc }} ms-2">{{ ucfirst($mrp->status) }}</span>
            </h5>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.procurement.create-from-mrp', $mrp->id) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-cart-plus"></i> Create POs
                </a>
                <a href="{{ route('admin.mrp.index') }}" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Period</small><strong>{{ $mrp->period_from ?? 'N/A' }} → {{ $mrp->period_to ?? 'N/A' }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Total Materials</small><strong>{{ $mrp->total_materials }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Total Cost</small><strong class="text-primary">{{ number_format($mrp->total_cost, 0) }} đ</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Created</small><strong>{{ $mrp->created_at ? \Carbon\Carbon::parse($mrp->created_at)->format('d/m/Y') : '' }}</strong></div>
                @if($mrp->notes)
                    <div class="col-12"><small class="text-muted d-block">Notes</small><p class="mb-0">{{ $mrp->notes }}</p></div>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary by Type -->
    <div class="row g-3 mb-4">
        @foreach($summaryByType as $type => $s)
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-{{ $type === 'fabric' ? 'primary' : ($type === 'lining' ? 'info' : ($type === 'trim' ? 'warning' : 'secondary')) }} text-white">
                    <div class="card-body">
                        <h6 class="card-title">{{ ucfirst($type) }}</h6>
                        <h4 class="mb-0">{{ number_format($s['net'], 0) }}</h4>
                        <small>{{ $s['count'] }} items | Gross: {{ number_format($s['gross'], 0) }}</small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Items Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Material Requirements ({{ count($items) }} items)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Unit</th>
                        <th class="text-end">Gross Req.</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">On Order</th>
                        <th class="text-end">Allocated</th>
                        <th class="text-end text-danger">Net Req.</th>
                        <th class="text-end">Safety</th>
                        <th class="text-end fw-bold">Planned Order</th>
                        <th>Lead Time</th>
                        <th>Receipt Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td><code>{{ $item->material_code }}</code></td>
                            <td><small>{{ $item->material_name }}</small></td>
                            <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end">{{ number_format($item->gross_requirement, 2) }}</td>
                            <td class="text-end">{{ number_format($item->on_hand_stock, 2) }}</td>
                            <td class="text-end">{{ number_format($item->on_order_stock, 2) }}</td>
                            <td class="text-end">{{ number_format($item->allocated_stock, 2) }}</td>
                            <td class="text-end text-danger fw-bold">{{ number_format($item->net_requirement, 2) }}</td>
                            <td class="text-end">{{ number_format($item->safety_stock, 2) }}</td>
                            <td class="text-end fw-bold text-primary">{{ number_format($item->planned_order_qty, 2) }}</td>
                            <td>{{ $item->lead_time_days }}d</td>
                            <td><small>{{ $item->order_receipt_date }}</small></td>
                            <td>
                                @php $sc = match($item->status) { 'ordered'=>'info', 'received'=>'success', default=>'secondary' } @endphp
                                <span class="badge bg-{{ $sc }}">{{ $item->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-boxes me-2"></i>Lot/Roll allocation proposal</h6></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Material ID</th><th>Color</th><th>Lot/Roll</th><th>Location</th><th class="text-end">Proposed qty</th></tr></thead><tbody>
        @forelse($lotAllocations as $allocation)<tr><td>{{ $allocation->material_id }}</td><td>{{ $allocation->material_color ?? '-' }}</td><td>{{ $allocation->lot_roll_no ?? '-' }}</td><td>{{ $allocation->location ?? '-' }}</td><td class="text-end">{{ number_format($allocation->allocated_qty, 4) }}</td></tr>
        @empty<tr><td colspan="5" class="text-center py-3 text-muted">No on-hand lot is proposed; planned supply may come from open POs.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>
@endsection
