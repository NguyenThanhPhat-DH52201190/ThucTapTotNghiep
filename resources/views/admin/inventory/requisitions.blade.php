@extends('layouts.app')
@section('title', 'Material Requisitions & Issues')
@section('content')
@php($canManage = auth()->user()->role === 'admin' || auth()->user()->role === 'warehouse')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div><h5 class="mb-1 fw-bold"><i class="bi bi-clipboard-check me-2"></i>Material Requisitions &amp; Warehouse Issue</h5><small class="text-muted">Issue each required material from a matching color, size and lot.</small></div>
            <div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.inventory.index') }}">Inventory</a><a class="btn btn-outline-info btn-sm" href="{{ route('admin.inventory.transactions') }}">Transactions</a></div>
        </div>
    </div>

    <form method="GET" class="mb-3 d-flex gap-2 align-items-center"><select name="status" class="form-select" style="max-width:220px"><option value="">All statuses</option>@foreach(['pending','partial','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select><button class="btn btn-dark">Filter</button><a href="{{ route('admin.inventory.requisitions') }}" class="btn btn-outline-secondary">Reset</a></form>

    <div class="accordion mb-4" id="requisitionList">
        @forelse($requisitions as $requisition)
            @php($items = $itemsByRequisition[$requisition->id] ?? collect())
            @php($badge = match($requisition->status) { 'completed' => 'success', 'partial' => 'warning', 'cancelled' => 'secondary', default => 'primary' })
            <div class="accordion-item shadow-sm border-0 mb-2">
                <h2 class="accordion-header" id="reqHeading{{ $requisition->id }}"><button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#req{{ $requisition->id }}"><span class="fw-semibold me-2">{{ $requisition->requisition_code }}</span><span class="me-2">CS {{ $requisition->CS }} · {{ $requisition->SNo }}</span><span class="badge bg-{{ $badge }}">{{ ucfirst($requisition->status) }}</span></button></h2>
                <div id="req{{ $requisition->id }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#requisitionList"><div class="accordion-body">
                    <div class="mb-3 text-muted small">Order color: <strong>{{ $requisition->order_color ?: '-' }}</strong> · {{ $requisition->Sname }}</div>
                    <form method="POST" action="{{ route('admin.inventory.issues.store') }}">@csrf
                        <input type="hidden" name="requisition_id" value="{{ $requisition->id }}">
                        <div class="row g-2 mb-3"><div class="col-md-5"><label class="form-label">Receiver / production line</label><input name="receiver_name" class="form-control" placeholder="e.g. Line Blue"></div></div>
                        <div class="table-responsive"><table class="table table-sm align-middle"><thead class="table-light"><tr><th>Material</th><th>Required spec</th><th class="text-end">Requested</th><th class="text-end">Issued</th><th>Lot / available stock</th><th style="width:150px">Issue qty</th></tr></thead><tbody>
                            @foreach($items as $index => $item)
                                @php($remaining = max(0, $item->requested_qty - $item->issued_qty))
                                @php($balances = ($balancesByMaterial[$item->material_id] ?? collect())->filter(fn ($balance) => ($item->material_color === null || $balance->material_color === $item->material_color) && ($item->material_size === null || $balance->material_size === $item->material_size)))
                                <tr><td><code>{{ $item->internal_code }}</code><br><small>{{ $item->material_name }}</small></td><td>{{ $item->material_color ?: '-' }} / {{ $item->material_size ?: '-' }}</td><td class="text-end">{{ number_format($item->requested_qty, 4) }} {{ $item->unit }}</td><td class="text-end">{{ number_format($item->issued_qty, 4) }}</td><td>@if($remaining > 0)<input type="hidden" name="items[{{ $index }}][requisition_item_id]" value="{{ $item->id }}"><select class="form-select form-select-sm" name="items[{{ $index }}][balance_id]" required><option value="">{{ $balances->isEmpty() ? 'No matching stock' : 'Select lot' }}</option>@foreach($balances as $balance)<option value="{{ $balance->id }}">{{ $balance->lot_roll_no ?: 'No lot' }} · {{ $balance->location_code ?: $balance->location ?: '-' }} · Avail {{ number_format($balance->balance_qty - $balance->reserved_qty, 4) }}</option>@endforeach</select>@else<span class="text-success small">Fully issued</span>@endif</td><td>@if($remaining > 0)<input class="form-control form-control-sm" type="number" min="0.0001" step="0.0001" max="{{ $remaining }}" name="items[{{ $index }}][quantity]" value="{{ $remaining }}" required>@else-@endif</td></tr>
                            @endforeach
                        </tbody></table></div>
                        @if($canManage && in_array($requisition->status, ['pending', 'partial']))<button class="btn btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Post material issue</button>@endif
                    </form>
                    @if($canManage && in_array($requisition->status, ['pending', 'partial']))<form method="POST" action="{{ route('admin.inventory.requisitions.cancel', $requisition->id) }}" class="mt-2" onsubmit="return confirm('Cancel this requisition and release remaining reserved stock?')">@csrf<button class="btn btn-outline-danger btn-sm">Cancel requisition</button></form>@endif
                </div></div>
            </div>
        @empty
            <div class="alert alert-info">No material requisitions have been created yet. Release an OCS with a valid BOM, then run the queue worker.</div>
        @endforelse
    </div>

    <div class="card shadow-sm border-0"><div class="card-header bg-white"><strong><i class="bi bi-clock-history me-2"></i>Recent issue history</strong></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Issue</th><th>Requisition</th><th>CS</th><th>Date</th><th>Receiver</th><th>Status</th></tr></thead><tbody>@forelse($issues as $issue)<tr><td class="fw-semibold">{{ $issue->issue_code }}</td><td>{{ $issue->requisition_code }}</td><td>{{ $issue->CS }}</td><td>{{ $issue->issue_date }}</td><td>{{ $issue->receiver_name ?: '-' }}</td><td><span class="badge bg-success">{{ ucfirst($issue->status) }}</span></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-3">No material issues posted.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
