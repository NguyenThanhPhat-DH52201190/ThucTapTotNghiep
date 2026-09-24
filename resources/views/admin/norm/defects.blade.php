@extends('layouts.app')
@section('title', 'NORM - Material defects')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h4>Material defects — {{ $order->CS }}</h4><strong>Style: {{ $order->SNo }} · Product Qty: {{ number_format($order->Qty) }}</strong></div><a class="btn btn-outline-secondary align-self-start" href="{{ route('admin.norm.materials.show', $order->id) }}">Back to NORM</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><div>Please select evidence files again before saving.</div></div>@endif
<div class="card mb-4"><div class="card-body">
    <h5>Record material defects</h5>
    <p class="text-muted">Enter quantities in the material's unit. Replacement is a request only; saving does not change stock, Yield or Waste.</p>
    @if($materials->isEmpty())<div class="alert alert-info">No current NORM materials available. Open NORM after assigning a BOM first.</div>@else
    <form method="POST" action="{{ route('admin.norm.defects.store', $order->id) }}" enctype="multipart/form-data" id="defectForm">
        @csrf <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="mb-3"><label for="occurredOn" class="form-label">Date *</label><input id="occurredOn" type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" style="max-width:240px" required></div>
        <div id="defectRows">@foreach(old('items', [[]]) as $index => $item)@include('admin.norm.partials.defect-row')@endforeach</div>
        <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" id="addDefect">+ Add material</button><button class="btn btn-primary" type="submit">Save defects</button></div>
    </form>
    <template id="defectTemplate">@include('admin.norm.partials.defect-row', ['index' => 0, 'item' => []])</template>
    @endif
</div></div>
<h5>Defect history</h5>
<div class="card"><div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Date / Recorded by</th><th>Material</th><th>Colour / Size</th><th>Unit</th><th>Defective qty</th><th>Requested replacement</th><th>Disposition</th><th>Reason</th><th>Image</th></tr></thead><tbody>
@forelse($history as $row)<tr>
    <td>{{ $row->occurred_on }}<div class="small text-muted">{{ $row->recorded_by ?? '—' }}<br>{{ $row->created_at }}</div></td>
    <td><strong>{{ $row->material_code }}</strong><div>{{ $row->material_name }}</div></td><td>{{ $row->material_color ?: '—' }} / {{ $row->material_size ?: '—' }}</td><td>{{ $row->unit }}</td>
    <td>{{ rtrim(rtrim(number_format($row->defect_qty, 4), '0'), '.') }}</td><td>{{ rtrim(rtrim(number_format($row->replacement_qty, 4), '0'), '.') }}</td>
    <td>{{ ['scrap' => 'Scrap', 'reuse' => 'Reuse', 'return_supplier' => 'Return to supplier'][$row->disposition] ?? $row->disposition }}</td><td style="white-space:pre-wrap;min-width:180px">{{ $row->reason }}</td>
    <td>@if($row->image_path)<a href="{{ route('admin.norm.defects.image', [$order->id, $row->id]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.norm.defects.image', [$order->id, $row->id]) }}" alt="Defect evidence" style="width:70px;height:70px;object-fit:contain" loading="lazy"></a>@else — @endif</td>
</tr>@empty<tr><td colspan="9" class="text-center text-muted">No material defects recorded.</td></tr>@endforelse
</tbody></table></div><div class="card-footer">{{ $history->links() }}</div></div>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.getElementById('defectForm');
    if (!form) return;
    const rows = document.getElementById('defectRows'), add = document.getElementById('addDefect');
    function reindex() {
        const entries = rows.querySelectorAll('.defect-row');
        entries.forEach((row, i) => {
            row.querySelectorAll('[data-field]').forEach(input => input.name = `items[${i}][${input.dataset.field}]`);
            row.querySelector('[data-remove-defect]').disabled = entries.length === 1;
        });
        add.disabled = entries.length >= 20;
    }
    add.addEventListener('click', () => {
        if (rows.children.length >= 20) return;
        rows.append(document.getElementById('defectTemplate').content.cloneNode(true)); reindex();
    });
    rows.addEventListener('click', e => {
        if (e.target.closest('[data-remove-defect]') && rows.children.length > 1) { e.target.closest('.defect-row').remove(); reindex(); }
    });
    form.addEventListener('submit', () => form.querySelector('[type="submit"]').disabled = true);
    window.addEventListener('pageshow', () => form.querySelector('[type="submit"]').disabled = false);
    reindex();
})();
</script>
@endpush
