@extends('layouts.app')
@section('title', 'Procurement')
@section('content')
@php
    $user = auth()->user();
    $canCreatePo = $user->role === 'admin' || $user->role === 'warehouse' || ($user->role === 'ppic' && in_array($user->ppic_team, ['create', 'both'], true));
    $canManagePo = $user->role === 'admin';
@endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2"></i>Purchase Orders</h5>
            <div class="d-flex gap-2">
                @if($user->role === 'admin')<a href="{{ route('admin.procurement.suppliers') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-people"></i> Suppliers
                </a>@endif
                @if($canCreatePo)
                <a href="{{ route('admin.procurement.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Create PO
                </a>@endif
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    @if(request('show_closed'))<input type="hidden" name="show_closed" value="1">@endif
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(['draft','sent','confirmed','partial','received','closed','cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Filter</button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                    @if(request('status') !== 'closed')
                        @php
                            $closedToggleQuery = request()->except(['show_closed', 'page']);
                            if (!request()->boolean('show_closed')) $closedToggleQuery['show_closed'] = 1;
                            $closedToggleUrl = url()->current() . (count($closedToggleQuery) ? '?' . http_build_query($closedToggleQuery) : '');
                        @endphp
                        <a href="{{ $closedToggleUrl }}" class="btn btn-outline-secondary">
                            <i class="bi bi-{{ request()->boolean('show_closed') ? 'eye-slash' : 'eye' }} me-1"></i>{{ request()->boolean('show_closed') ? 'Hide Closed PO' : 'Show Closed PO' }}
                        </a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Expected Delivery</th>
                        <th class="text-end">Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pos as $po)
                        <tr>
                            <td class="fw-bold"><a href="{{ route('admin.procurement.show', $po->id) }}">{{ $po->po_number }}</a></td>
                            <td><small>{{ $po->supplier_code }} - {{ $po->supplier_name }}</small></td>
                            <td>{{ $po->order_date }}</td>
                            <td><small>{{ $po->expected_delivery ?? '-' }}</small></td>
                            <td class="text-end fw-bold">{{ number_format((float) $po->total_amount * (1 + (float) ($po->vat_percent ?? 0) / 100), 4) }} {{ $po->currency ?? 'USD' }}</td>
                            <td>
                                @php $sc = match($po->status) { 'sent'=>'info', 'confirmed'=>'primary', 'received'=>'success', 'partial'=>'warning', 'closed'=>'dark', 'cancelled'=>'danger', default=>'secondary' } @endphp
                                <span class="badge bg-{{ $sc }}">{{ ucfirst($po->status) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.procurement.show', $po->id) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                                @if($canManagePo)
                                    <a href="{{ route('admin.procurement.edit', $po->id) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                @endif
                                @if($canManagePo)
                                    <form method="POST" action="{{ route('admin.procurement.destroy', $po->id) }}" class="d-inline" onsubmit="return confirm('Delete this PO?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">No purchase orders</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $pos->links() }}</div>
    </div>
</div>
@endsection
