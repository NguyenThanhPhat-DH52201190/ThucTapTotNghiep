@extends('layouts.app')
@section('title', 'NORM - Materials')
@section('content')
@include('admin.partials.image-popover')
@php($editingNorm = request()->boolean('edit'))
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="py-2">
                <h5 class="mb-3 fw-bold"><i class="bi bi-rulers me-2"></i>NORM Materials</h5>
                <div class="d-flex flex-wrap gap-2">
                    <span class="border rounded bg-primary-subtle text-primary-emphasis px-3 py-2">CS: <strong>@include('admin.partials.image-trigger', ['imageUrl' => !empty($order->image_path) ? route('admin.ocs.image', $order->id, false) : null, 'imageLabel' => $order->CS])</strong></span>
                    <span class="border rounded bg-light px-3 py-2">Style: <strong>{{ $order->SNo }}</strong></span>
                    <span class="border rounded bg-success-subtle text-success-emphasis px-3 py-2">Product Qty: <strong>{{ number_format($order->Qty, 0) }}</strong></span>
                </div>
            </div>
            <div class="d-flex gap-2"><a href="{{ route('admin.norm.materials') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>CS List</a><a href="{{ route('admin.norm.materials.export', ['cutsheet_id' => $order->id]) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a></div>
        </div>
    </div>

    <div class="mb-3"><span class="text-muted">BOM:</span> @include('admin.partials.image-trigger', ['imageUrl' => $order->bom_image_id ? route('admin.bom.image', $order->bom_image_id, false) : null, 'imageLabel' => $order->bom_style . ' / ' . $order->bom_version])</div>
    <div class="mb-3">
        @if($editingNorm)
            <form id="normConfirmedForm" method="POST" action="{{ route('admin.norm.materials.confirmed', ['id' => $order->id, 'page' => request('page', 1)]) }}">
                @csrf @method('PUT')
                <p class="small text-muted">Blank confirmed values use BOM values. Save applies to the rows on this page. Required quantities update after saving.</p>
                <label for="normReason" class="form-label">Change reason</label>
                <input id="normReason" name="reason" value="{{ old('reason') }}" maxlength="1000" class="form-control mb-2">
                <button class="btn btn-primary" @disabled($rows->isEmpty())>Save NORM</button>
                <a href="{{ route('admin.norm.materials.show', ['id' => $order->id, 'page' => request('page', 1)]) }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        @else
            <a href="{{ route('admin.norm.materials.show', ['id' => $order->id, 'page' => request('page', 1), 'edit' => 1]) }}" class="btn btn-primary">Edit NORM</a>
        @endif
    </div>
    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Material</th><th>Description</th><th>Type</th><th>Colour / Size</th><th>Unit</th><th class="text-end">Yield plan</th><th class="text-end">Yield confirmed</th><th class="text-end">watse confirmed</th><th class="text-end">Required</th><th class="text-end">Available</th><th class="text-end">Shortage</th></tr></thead>
        <tbody>@forelse($rows as $row)<tr>
            <td><code>@include('admin.partials.material-image-trigger', ['imageLabel' => $row->material_code])</code></td><td>@include('admin.partials.material-image-trigger', ['imageLabel' => $row->material_name])</td><td><span class="badge bg-info">{{ ucfirst($row->material_type) }}</span></td>
            <td>{{ $row->material_color ?: '-' }} / {{ $row->material_size ?: '-' }}</td><td>{{ $row->unit }}</td>
            <td class="text-end">{{ number_format($row->yield_plan ?? $row->consumption_rate, 4) }}
                @if(isset($row->confirmation_revision) && ((float) $row->yield_plan !== (float) $row->bom_yield_at_confirmation || (float) $row->waste_plan !== (float) $row->bom_waste_at_confirmation))<div class="small text-warning">BOM changed; review confirmed values</div>@endif
            </td>
            <td class="text-end">
                @if($editingNorm)
                    <input type="hidden" form="normConfirmedForm" name="rows[{{ $loop->index }}][bom_item_id]" value="{{ $row->bom_item_id }}">
                    <input type="hidden" form="normConfirmedForm" name="rows[{{ $loop->index }}][revision]" value="{{ $row->confirmation_revision ?? 0 }}">
                    <input type="hidden" form="normConfirmedForm" name="rows[{{ $loop->index }}][yield_plan]" value="{{ $row->yield_plan }}">
                    <input type="hidden" form="normConfirmedForm" name="rows[{{ $loop->index }}][waste_plan]" value="{{ $row->waste_plan }}">
                    <input type="number" form="normConfirmedForm" name="rows[{{ $loop->index }}][yield_confirmed]" value="{{ old('rows.'.$loop->index.'.yield_confirmed', $row->yield_confirmed) }}" min="0" max="99999999" step="0.0001" class="form-control text-end" placeholder="Use plan" aria-label="Yield confirmed for {{ $row->material_code }}">
                @else {{ isset($row->yield_confirmed) ? number_format($row->yield_confirmed, 4) : '—' }} @endif
            </td>
            <td class="text-end">
                @if($editingNorm)
                    <input type="number" form="normConfirmedForm" name="rows[{{ $loop->index }}][waste_confirmed]" value="{{ old('rows.'.$loop->index.'.waste_confirmed', $row->waste_confirmed) }}" min="0" max="100" step="0.01" class="form-control text-end" placeholder="{{ number_format($row->waste_plan, 2) }}" aria-label="watse confirmed for {{ $row->material_code }}">
                    <small class="text-muted">BOM: {{ number_format($row->waste_plan, 2) }}%</small>
                @else {{ number_format($row->waste_percent, 2) }}% @if(!isset($row->waste_confirmed))<small class="text-muted">(BOM)</small>@endif @endif
            </td>
            <td class="text-end fw-bold">{{ number_format($row->required_qty, 0) }}</td><td class="text-end">{{ number_format($row->available_qty, 0) }}</td><td class="text-end fw-bold {{ $row->shortage_qty > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($row->shortage_qty, 0) }}</td>
        </tr>@empty<tr><td colspan="11" class="text-center text-muted py-4">No material requirements found.</td></tr>@endforelse</tbody>
    </table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div>
@endsection
