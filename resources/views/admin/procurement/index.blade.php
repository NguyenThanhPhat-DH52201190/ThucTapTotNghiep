@extends('layouts.app')
@section('title', 'Procurement')
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2"></i>Purchase Orders</h5>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.procurement.suppliers') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-people"></i> Suppliers
                </a>
                <a href="{{ route('admin.procurement.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Create PO
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(['draft','sent','confirmed','partial','received','cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Filter</button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
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
                            <td class="text-end fw-bold">{{ number_format($po->total_amount, 0) }} đ</td>
                            <td>
                                @php $sc = match($po->status) { 'sent'=>'info', 'confirmed'=>'primary', 'received'=>'success', 'partial'=>'warning', 'cancelled'=>'danger', default=>'secondary' } @endphp
                                <span class="badge bg-{{ $sc }}">{{ ucfirst($po->status) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.procurement.show', $po->id) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
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
