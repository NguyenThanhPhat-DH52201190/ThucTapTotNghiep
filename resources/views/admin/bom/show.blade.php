@extends('layouts.app')
@section('title', 'BOM Detail - ' . $bom->style_no)
@section('content')
@include('admin.partials.customer-style-selector')

@php $canManage = in_array(auth()->user()->role, ['admin', 'ppic'], true); @endphp

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-file-text me-2"></i>BOM: {{ $bom->style_no }} - {{ $bom->style_name }}
            </h5>
            <div class="d-flex gap-2">
                @if($canManage)
                    <a href="{{ route('admin.bom.edit', $bom->id) }}" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cloneBomModal">Clone BOM</button>
                @endif
                <a href="{{ route('admin.bom.export', $bom->id) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-download"></i> Export Excel
                </a>
                <a href="{{ route('admin.bom.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Style No</label>
                    <p class="mb-0 fs-5 fw-bold">{{ $bom->style_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Style Name</label>
                    <p class="mb-0">{{ $bom->style_name }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Customer</label>
                    <p class="mb-0">{{ $bom->customer }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Status</label>
                    <p class="mb-0">
                        @if($bom->status === 'active')
                            <span class="badge bg-success">Active</span>
                        @elseif($bom->status === 'draft')
                            <span class="badge bg-warning text-dark">Draft</span>
                        @else
                            <span class="badge bg-secondary">Archived</span>
                        @endif
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Version</label>
                    <p class="mb-0">{{ $bom->version }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Effective Date</label>
                    <p class="mb-0">{{ $bom->effective_date ? \Carbon\Carbon::parse($bom->effective_date)->format('d/m/Y') : '-' }}</p>
                </div>
                @if($canManage)
                    <div class="col-md-3">
                        <label class="fw-semibold text-muted small">Total Fabric Cost</label>
                        <p class="mb-0 fw-bold text-primary">$ {{ number_format($bom->total_fabric_cost, 4) }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-semibold text-muted small">Total Trim Cost</label>
                        <p class="mb-0 fw-bold text-success">$ {{ number_format($bom->total_trim_cost, 4) }}</p>
                    </div>
                @endif
                @if($bom->notes)
                <div class="col-12">
                    <label class="fw-semibold text-muted small">Notes</label>
                    <p class="mb-0">{{ $bom->notes }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- BOM Items -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Materials List ({{ count($items) }} items)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Colour</th>
                        <th>Material Size</th>
                        <th>Product Sizes</th>
                        <th>Width</th>
                        <th>Unit</th>
                        <th>Yield (ĐM)</th>
                        <th>Waste %</th>
                        <th>Total</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                            <td><code>{{ $item->material_code }}</code></td>
                            <td>{{ $item->material_name }}</td>
                            <td>{{ $item->colour ?? '-' }}</td>
                            <td>{{ $item->size ?? '-' }}</td>
                            <td>{{ ($itemSizeNames[$item->id] ?? collect())->pluck('size_name')->implode(', ') ?: 'All' }}</td>
                            <td>{{ $item->width ?? '-' }}</td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end">{{ number_format($item->consumption_rate, 4) }}</td>
                            <td class="text-end">{{ $item->waste_percent ? number_format($item->waste_percent, 1) . '%' : '-' }}</td>
                            <td class="text-end fw-bold">{{ number_format((float) $item->consumption_rate * (1 + (float) $item->waste_percent / 100), 4) }}</td>
                            <td><small>{{ $item->remark ?? '' }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>


</div>

@if($canManage)
<div class="modal fade" id="cloneBomModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('admin.bom.clone', $bom->id) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Clone BOM</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">New Style No</label><select name="style_no" id="style_no" class="form-select" data-customer-style data-current="{{ old('style_no', $bom->style_no ?? '') }}" required><option value="">-- Select Style --</option></select></div>
        <div class="col-12"><label class="form-label">Style Name</label><input name="style_name" class="form-control" value="{{ $bom->style_name }}"></div>
        <div class="col-md-7"><label class="form-label">Customer Master</label><select name="customer_id" class="form-select"><option value="">-- Select customer --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) $bom->customer_id === (string) $customer->id)>{{ $customer->name }}{{ $customer->brand ? ' — ' . $customer->brand : '' }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">Version</label><input name="version" class="form-control" value="V1"></div>
    </div><div class="modal-footer"><button class="btn btn-primary">Create draft clone</button></div>
</form></div></div>
@endif

@endsection
