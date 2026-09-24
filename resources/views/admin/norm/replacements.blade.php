@extends('layouts.app')
@section('title', 'NORM - Replace material')
@section('content')
<div class="d-flex justify-content-between gap-2 mb-3"><h4>Replace material ? {{ $order->CS }}</h4><a href="{{ route('admin.norm.materials.show', $order->id) }}" class="btn btn-outline-secondary">Back to NORM</a></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><div class="card-body">
<form method="POST" action="{{ route('admin.norm.replacements.store', $order->id) }}" id="replacementForm">
@csrf
<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="fingerprint" id="replacementFingerprint">
<div class="row g-3">
<div class="col-md-6"><label for="replacementSource" class="form-label">Original material *</label><select id="replacementSource" name="bom_item_id" class="form-select" required><option value="">Select material</option>@foreach($sources as $source)<option value="{{ $source->bom_item_id }}" @selected(old('bom_item_id', request('bom_item_id')) == $source->bom_item_id)>{{ $source->material_code }} ? {{ $source->material_name }}</option>@endforeach</select><small id="sourceDetails" class="text-muted"></small></div>
<div class="col-md-6"><label for="replacementMode" class="form-label">Replacement purpose *</label><select id="replacementMode" name="mode" class="form-select"><option value="remaining" @selected(old('mode') !== 'defect')>Replace unissued requirement</option><option value="defect" @selected(old('mode') === 'defect')>Replace recorded defective material</option></select></div>
<div class="col-12" id="replacementDefectGroup" hidden><label for="replacementDefect" class="form-label">Material defect *</label><select id="replacementDefect" name="defect_id" class="form-select"></select></div>
<div class="col-md-6"><label for="replacementQty" class="form-label">Original material quantity to replace *</label><input id="replacementQty" name="source_qty" value="{{ old('source_qty') }}" type="number" min="0.0001" max="99999999" step="0.0001" class="form-control" required><button id="useRemaining" class="btn btn-sm btn-outline-secondary mt-2" type="button">Use all remaining</button></div>
<div class="col-md-6"><label for="replacementMaterial" class="form-label">Replacement material *</label><select id="replacementMaterial" name="material_id" class="form-select" required><option value="">Select replacement</option>@foreach($materials as $material)<option value="{{ $material->id }}" @selected(old('material_id') == $material->id)>{{ $material->internal_code }} ? {{ $material->material_name }} / {{ $material->color }} / {{ $material->size }} ({{ $material->unit }})</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label" for="replacementYield">Yield confirmed (new material) *</label><input id="replacementYield" name="yield_confirmed" value="{{ old('yield_confirmed') }}" type="number" min="0.0001" max="99999999" step="0.0001" class="form-control" required></div>
<div class="col-md-6"><label class="form-label" for="replacementWaste">Waste confirmed (%) *</label><input id="replacementWaste" name="waste_confirmed" value="{{ old('waste_confirmed', 0) }}" type="number" min="0" max="100" step="0.01" class="form-control" required></div>
<div class="col-12"><div class="alert alert-info mb-0" id="replacementPreview">Select materials and enter quantities to preview the replacement requirement.</div><small class="text-muted">New requirement = original quantity ? [original yield ? (1 + original waste / 100)] ? new yield ? (1 + new waste / 100). Check the new material unit before saving.</small></div>
<div class="col-12"><label for="replacementReason" class="form-label">Reason *</label><textarea id="replacementReason" name="reason" class="form-control" maxlength="1000" required>{{ old('reason') }}</textarea></div>
<div class="col-12"><p class="text-muted">Applies only to this CU. Recorded defects add replacement demand without deducting the original issued quantity again. Existing reservations and requisitions need a separate review; stock is deducted only when you confirm a delivery bill.</p><button class="btn btn-primary" @disabled($sources->isEmpty())>Save replacement</button></div>
</div></form></div></div>
<h5>Replacement history</h5>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>No</th><th>Original</th><th>Original qty</th><th>Replacement</th><th>Required</th><th>Purpose</th><th>Reason</th><th>Created</th></tr></thead><tbody>
@forelse($history as $entry)
@php($before = json_decode($entry->source_snapshot)) @php($after = json_decode($entry->material_snapshot))
<tr><td>{{ $entry->id }}</td><td>{{ $before->material_code }}</td><td>{{ $entry->source_qty }} {{ $before->unit }}</td><td>{{ $after->material_code }}</td><td>{{ $entry->required_qty }} {{ $after->unit }}</td><td>{{ $entry->defect_id ? 'Defect #'.$entry->defect_id : 'Unissued requirement' }}</td><td>{{ $entry->reason }}</td><td>{{ $entry->created_at }}</td></tr>
@empty<tr><td colspan="8">No replacements.</td></tr>@endforelse
</tbody></table></div>
@endsection
@push('scripts')
<script>
(() => {
    const sources = @json($sources), defects = @json($defects), materials = @json($materials);
    const source = document.getElementById('replacementSource'), mode = document.getElementById('replacementMode');
    const defect = document.getElementById('replacementDefect'), qty = document.getElementById('replacementQty');
    const material = document.getElementById('replacementMaterial'), rate = document.getElementById('replacementYield'), waste = document.getElementById('replacementWaste');
    let limit = 0;
    function refreshSource() {
        const row = sources.find(row => String(row.bom_item_id) === source.value);
        document.getElementById('replacementFingerprint').value = row?.fingerprint || '';
        document.getElementById('sourceDetails').textContent = row ? `Remaining: ${row.remaining} ${row.unit}. Yield: ${row.consumption_rate}; waste: ${row.waste_percent}%.` : '';
        defect.replaceChildren(new Option('Select defect', ''));
        defects.filter(d => String(d.bom_item_id) === source.value && Number(d.remaining) > 0).forEach(d => defect.add(new Option(`#${d.id} ? ${d.occurred_on}: ${d.remaining} ${d.unit} ? ${d.reason}`, d.id)));
        refresh();
    }
    function refresh() {
        const row = sources.find(row => String(row.bom_item_id) === source.value);
        const recorded = defects.find(d => String(d.id) === defect.value);
        const replacement = materials.find(m => String(m.id) === material.value);
        defect.required = mode.value === 'defect'; defect.disabled = !defect.required;
        document.getElementById('replacementDefectGroup').hidden = !defect.required;
        limit = Number(defect.required ? recorded?.remaining || 0 : row?.remaining || 0);
        qty.max = limit;
        const factor = Number(row?.consumption_rate) * (1 + Number(row?.waste_percent) / 100);
        const required = factor > 0 ? Number(qty.value) / factor * Number(rate.value) * (1 + Number(waste.value) / 100) : 0;
        document.getElementById('replacementPreview').textContent = replacement && required > 0 ? `New requirement: ${required.toFixed(4)} ${replacement.unit} (${replacement.internal_code}). Maximum original quantity: ${limit} ${row.unit}.` : 'Select materials and enter quantities to preview the replacement requirement.';
    }
    source.addEventListener('change', refreshSource);
    [mode, defect, qty, material, rate, waste].forEach(el => el.addEventListener('input', refresh));
    document.getElementById('useRemaining').onclick = () => { qty.value = limit; refresh(); };
    refreshSource(); defect.value = @json((string) old('defect_id', '')); refresh();
})();
</script>
@endpush
