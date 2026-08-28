@extends('layouts.app')
@section('title', 'BOM Detail - ' . $bom->style_no)
@section('content')

@php $canManage = auth()->user()->role === 'admin'; @endphp

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
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Total Fabric Cost</label>
                    <p class="mb-0 fw-bold text-primary">{{ number_format($bom->total_fabric_cost, 0) }} đ</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-semibold text-muted small">Total Trim Cost</label>
                    <p class="mb-0 fw-bold text-success">{{ number_format($bom->total_trim_cost, 0) }} đ</p>
                </div>
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
                        <th>Code</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Colour</th>
                        <th>Size</th>
                        <th>Width</th>
                        <th>Unit</th>
                        <th>Yield (ĐM)</th>
                        <th>Waste %</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td><code>{{ $item->material_code }}</code></td>
                            <td>{{ $item->material_name }}</td>
                            <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                            <td>{{ $item->colour ?? '-' }}</td>
                            <td>{{ $item->size ?? '-' }}</td>
                            <td>{{ $item->width ?? '-' }}</td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end">{{ number_format($item->consumption_rate, 4) }}</td>
                            <td class="text-end">{{ $item->waste_percent ? number_format($item->waste_percent, 1) . '%' : '-' }}</td>
                            <td class="text-end">{{ number_format($item->unit_cost, 0) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->total_cost, 0) }}</td>
                            <td><small>{{ $item->remark ?? '' }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="10" class="text-end">TOTAL COST:</td>
                        <td class="text-end">{{ number_format($items->sum('unit_cost'), 0) }}</td>
                        <td class="text-end text-primary">{{ number_format($items->sum('total_cost'), 0) }} đ</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-palette me-2"></i>Garment-to-material colorways</h5></div>
        <div class="card-body">
            <p class="text-muted small">The mapping is used when an Order Cut Sheet is released: its garment color selects the correct material color for the requisition and MRP.</p>
            @if($colorways->isNotEmpty())
                <div class="table-responsive mb-3"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Material</th><th>Garment color</th><th>Material color</th><th>Note</th></tr></thead><tbody>
                @foreach($colorways as $colorway)<tr><td>{{ $colorway->material_code }} — {{ $colorway->material_name }}</td><td>{{ $colorway->garment_color }}</td><td>{{ $colorway->material_color }}</td><td>{{ $colorway->notes }}</td></tr>@endforeach
                </tbody></table></div>
            @endif
            @if($canManage)
                <form method="POST" action="{{ route('admin.bom.colorways.save', $bom->id) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4"><label class="form-label">BOM material</label><select name="colorways[0][bom_item_id]" class="form-select" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->material_code }} — {{ $item->material_name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Garment color</label><input name="colorways[0][garment_color]" class="form-control" required placeholder="e.g. GREEN"></div>
                    <div class="col-md-3"><label class="form-label">Material color</label><input name="colorways[0][material_color]" class="form-control" required placeholder="e.g. OLIVE"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Save mapping</button></div>
                    <div class="col-12"><input name="colorways[0][notes]" class="form-control" placeholder="Optional note"></div>
                    <div class="col-12"><input name="change_reason" class="form-control" placeholder="Change reason" required></div>
                </form>
            @endif
        </div>
    </div>

    <!-- Tech Pack Section -->
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2"></i>Tech Pack Info</h5>
        </div>
        <div class="card-body">
            @if($canManage)
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.bom.tech-pack.save', $bom->id) }}" class="row g-3">
                @csrf
                <div class="col-md-6"><label class="form-label">Size specification (JSON/text)</label><textarea name="size_spec" class="form-control" rows="3">{{ old('size_spec', $techPack->size_spec ?? '') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Colorways (JSON/text)</label><textarea name="color_way" class="form-control" rows="3">{{ old('color_way', $techPack->color_way ?? '') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Cutting instructions</label><textarea name="cutting_instructions" class="form-control" rows="3">{{ old('cutting_instructions', $techPack->cutting_instructions ?? '') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Sewing instructions</label><textarea name="sewing_instructions" class="form-control" rows="3">{{ old('sewing_instructions', $techPack->sewing_instructions ?? '') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Finishing instructions</label><textarea name="finishing_instructions" class="form-control" rows="3">{{ old('finishing_instructions', $techPack->finishing_instructions ?? '') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Packing instructions</label><textarea name="packing_instructions" class="form-control" rows="3">{{ old('packing_instructions', $techPack->packing_instructions ?? '') }}</textarea></div>
                <div class="col-md-4"><label class="form-label">Sample image</label><input type="file" name="sample_image" class="form-control" accept="image/*"></div>
                <div class="col-md-3"><label class="form-label">Approval status</label><select name="status" class="form-select"><option value="draft">Draft</option><option value="approved" {{ ($techPack->status ?? '') === 'approved' ? 'selected' : '' }}>Approved</option><option value="archived">Archived</option></select></div>
                <div class="col-md-5"><label class="form-label">Change reason</label><input name="change_reason" class="form-control" required></div>
                <div class="col-12"><button class="btn btn-primary btn-sm">Save Tech Pack</button></div>
            </form>
            @else
                <p class="text-muted mb-0">No Tech Pack has been recorded.</p>
            @endif
        </div>
    </div>
</div>

@if($canManage)
<div class="modal fade" id="cloneBomModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('admin.bom.clone', $bom->id) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Clone BOM</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">New Style No</label><input name="style_no" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Style Name</label><input name="style_name" class="form-control" value="{{ $bom->style_name }}"></div>
        <div class="col-md-7"><label class="form-label">Customer Master</label><select name="customer_id" class="form-select"><option value="">-- Select customer --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) $bom->customer_id === (string) $customer->id)>{{ $customer->name }}{{ $customer->brand ? ' — ' . $customer->brand : '' }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">Version</label><input name="version" class="form-control" value="V1"></div>
    </div><div class="modal-footer"><button class="btn btn-primary">Create draft clone</button></div>
</form></div></div>
@endif

@endsection
