@extends('layouts.app')
@section('title', 'Development Norms — '.$norm->cs)
@section('content')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card shadow-sm border-0 mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h5 class="fw-bold mb-2">Development Norms — {{ $norm->cs }}</h5>
            <div class="d-flex flex-wrap gap-2"><span class="badge bg-light text-dark border">Style: {{ $norm->style_no }}{{ $norm->style_name ? ' — '.$norm->style_name : '' }}</span><span class="badge bg-light text-dark border">Customer: {{ $norm->customer ?: '-' }}</span><span class="badge bg-success-subtle text-success-emphasis">Product Qty: {{ number_format($norm->product_qty, 0) }}</span></div>
        </div>
        <small class="text-muted">Copied from BOM {{ $norm->bom_header_id }} · {{ \Carbon\Carbon::parse($norm->copied_at)->format('d/m/Y H:i') }}</small>
    </div></div>
    <div class="alert alert-info py-2">Enter or adjust Yield and Waste % in this OCS copy. Total is calculated as Yield × (1 + Waste / 100). The source BOM is not modified.</div>
    <form method="POST" action="{{ route('admin.development-norms.update', $norm->cutsheet_id) }}">
        @csrf @method('PUT')
        <div class="card shadow-sm border-0"><div class="table-responsive">
            <table class="table table-sm table-bordered table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Material Code</th><th>Old Code</th><th>Description</th><th>Type</th><th>Colour / Size</th><th>Unit</th><th class="text-end">BOM Yield</th><th class="text-end">Development Yield</th><th class="text-end">BOM Waste %</th><th class="text-end">Development Waste %</th><th class="text-end">Total</th><th>Remark</th></tr></thead>
                <tbody>@forelse($items as $item)
                    @php
                        $yield = old('items.'.$item->id.'.yield_value', $item->yield_value);
                        $waste = old('items.'.$item->id.'.waste_percent', $item->waste_percent);
                        $total = (float) $yield * (1 + (float) $waste / 100);
                    @endphp
                    <tr>
                        <td><code>{{ $item->material_code }}</code></td><td><code>{{ $item->material_old_code ?: '-' }}</code></td><td>{{ $item->material_name }}</td>
                        <td><span class="badge bg-info">{{ ucfirst($item->material_type ?: 'other') }}</span></td><td>{{ $item->colour ?: '-' }} / {{ $item->size ?: '-' }}</td><td>{{ $item->unit ?: '-' }}</td>
                        <td class="text-end">{{ number_format($item->source_yield, 4) }}</td>
                        <td><input type="number" name="items[{{ $item->id }}][yield_value]" value="{{ $yield }}" min="0" max="99999999" step="0.0001" class="form-control form-control-sm text-end" required aria-label="Development yield for {{ $item->material_code }}"></td>
                        <td class="text-end">{{ number_format($item->source_waste_percent, 2) }}%</td>
                        <td><input type="number" name="items[{{ $item->id }}][waste_percent]" value="{{ $waste }}" min="0" max="100" step="0.01" class="form-control form-control-sm text-end" required aria-label="Development waste for {{ $item->material_code }}"></td>
                        <td class="text-end fw-bold">{{ number_format($total, 4) }}</td><td>{{ $item->remark ?: '-' }}</td>
                    </tr>
                @empty<tr><td colspan="12" class="text-center text-muted py-4">This OCS has no BOM material rows.</td>@endforelse</tbody>
            </table>
        </div><div class="card-footer d-flex justify-content-end"><button class="btn btn-primary" @disabled($items->isEmpty())><i class="bi bi-save me-1"></i>Save Development Norms</button></div></div>
    </form>
</div>
@endsection
