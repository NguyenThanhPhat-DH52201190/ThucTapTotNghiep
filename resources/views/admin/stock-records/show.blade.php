@extends('layouts.app')
@section('title', 'Stock Records - ' . $material->internal_code)
@section('content')
@php($onHand = (float) $balances->sum('balance_qty'))
@php($reserved = (float) $balances->sum('reserved_qty'))
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="fw-bold mb-1">{{ $material->internal_code }} — {{ $material->material_name }}</h4><span class="text-muted">{{ $material->color ?: '-' }} / {{ $material->size ?: '-' }} · Unit: {{ $material->unit }} · All warehouses and lots</span></div><a class="btn btn-outline-secondary" href="{{ route('admin.stock-records.index') }}">Stock Records</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3 mb-4">
@foreach(['Opening snapshot' => $record->opening_qty, 'On hand now' => $onHand, 'Reserved now' => $reserved, 'Available now' => max(0, $onHand - $reserved)] as $label => $qty)
<div class="col-md-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted">{{ $label }}</div><div class="fs-4 fw-bold">{{ number_format($qty, 0) }}</div></div></div></div>
@endforeach
</div>
@if(abs($ledgerQty - $onHand) > 0.0001)<div class="alert alert-warning">Inventory and recorded movements differ by {{ number_format($onHand - $ledgerQty, 0) }} {{ $material->unit }}. Review inventory adjustments; the opening snapshot has been preserved.</div>@endif

<div class="card shadow-sm border-0 mb-4"><div class="card-header bg-white"><h5 class="mb-0">Order / BOM priorities</h5></div>
<div class="card-body py-2"><label for="stockPlanSearch" class="form-label small">Find CS / BOM</label><input id="stockPlanSearch" class="form-control" style="max-width:420px" placeholder="CS code, BOM style or version"><div id="stockPlanSearchHint" class="small text-muted mt-1">Filtering does not change the allocation order or calculated quantities.</div></div><form method="POST" action="{{ route('admin.stock-records.priorities', $record->id) }}">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $record->revision }}">
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Priority</th><th>CS</th><th>BOM</th><th>Status</th><th>Ship date</th><th class="text-end">Required</th><th class="text-end">Already issued</th><th class="text-end">Remaining</th><th class="text-end">Own reservation used</th><th class="text-end">Can cover</th><th class="text-end">Shortage</th><th class="text-end">Projected total</th></tr></thead><tbody id="priorityRows">
@forelse($plan as $index => $line)<tr data-plan-search="{{ $line->order->CS }} {{ $line->bom?->style_no }} {{ $line->bom?->version }}">
<td><div class="d-flex gap-1"><input type="hidden" name="priorities[{{ $index }}][cutsheet_id]" value="{{ $line->order->id }}"><input aria-label="Priority for {{ $line->order->CS }}" class="form-control form-control-sm priority-value" style="width:75px" type="number" min="1" max="1000000" required name="priorities[{{ $index }}][sort_order]" value="{{ $line->priority }}"><button type="button" class="btn btn-sm btn-outline-secondary move-priority" data-direction="up" aria-label="Move {{ $line->order->CS }} up">↑</button><button type="button" class="btn btn-sm btn-outline-secondary move-priority" data-direction="down" aria-label="Move {{ $line->order->CS }} down">↓</button></div></td>
<td class="fw-semibold">{{ $line->order->CS }}</td><td>{{ $line->bom?->style_no }} / {{ $line->bom?->version }}</td><td>{{ ucfirst(str_replace('_', ' ', $line->order->status)) }}</td><td>{{ $line->order->expected_ship_date ?: '-' }}</td>
@foreach(['required', 'issued', 'remaining', 'reserved', 'covered', 'shortage', 'projected'] as $field)<td class="text-end {{ ($field === 'shortage' && $line->$field > 0) || ($field === 'projected' && $line->$field < 0) ? 'text-danger fw-bold' : '' }}">{{ number_format($line->$field, 0) }}</td>@endforeach
</tr>@empty<tr><td colspan="12" class="text-center text-muted py-4">No active order BOM uses this material.</td></tr>@endforelse
</tbody></table></div>
@if($plan->isNotEmpty())<div class="card-footer d-flex gap-3 align-items-center"><button class="btn btn-primary">Save priorities &amp; recalculate</button><span class="small text-muted" id="priorityHint"></span></div>@endif
</form></div>

<div class="card shadow-sm border-0 mb-4"><div class="card-header bg-white fw-bold">BOM templates using this material</div><div class="card-body d-flex gap-2 flex-wrap">@forelse($boms as $bom)<span class="badge bg-secondary" data-bom-search="{{ $bom->style_no }} {{ $bom->version }}">{{ $bom->style_no }} / {{ $bom->version }} · {{ $bom->status }}</span>@empty<span class="text-muted">No BOM templates found.</span>@endforelse</div></div>

<div class="card shadow-sm border-0 mb-4"><div class="card-header bg-white"><h5 class="mb-0">Stock history</h5></div>
<div class="card-body py-2 d-flex gap-3 align-items-center flex-wrap">
    <form method="GET" class="d-flex gap-2 align-items-center"><label for="historyScope" class="text-nowrap">History scope</label><select name="history" id="historyScope" class="form-select form-select-sm"><option value="tracking">Since opening snapshot</option><option value="all" @selected(request('history') === 'all')>All inventory history</option></select><button class="btn btn-sm btn-outline-primary">View</button></form>
    @if(request('history') === 'all')<span class="small text-muted">Balance before the first recorded movement: {{ number_format($historyOpening, 0) }} (derived from the opening snapshot and earlier movements).</span>@endif
</div>
<div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr><th>Date / Posted</th><th>Type</th><th>Document / CS</th><th>Color / Size / Lot</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Running balance</th><th>User</th><th>Notes</th></tr></thead><tbody>
@forelse($transactions as $entry)<tr><td>{{ $entry->transaction_date }}<div class="small text-muted">{{ $entry->created_at }}</div></td><td>{{ $entry->transaction_type }}</td><td>{{ $entry->reference_doc ?: $entry->reference_type }}@if($entry->reference_type === 'MATERIAL_ISSUE')<div class="small">{{ $issueOrders[$entry->reference_id] ?? '' }}</div>@endif</td><td>{{ $entry->material_color ?: '-' }} / {{ $entry->material_size ?: '-' }} / {{ $entry->lot_roll_no ?: '-' }}</td><td class="text-end">{{ $entry->quantity > 0 ? number_format($entry->quantity, 0) : '-' }}</td><td class="text-end">{{ $entry->quantity < 0 ? number_format(-$entry->quantity, 0) : '-' }}</td><td class="text-end fw-bold">{{ number_format($entry->running_qty, 0) }}</td><td>{{ $actors[$entry->created_by] ?? '-' }}</td><td>{{ $entry->notes }}</td></tr>
@empty<tr><td colspan="9" class="text-center text-muted py-4">No movements since the opening snapshot.</td></tr>@endforelse
</tbody></table></div><div class="card-footer">{{ $transactions->links() }}</div></div>

<div class="card shadow-sm border-0"><div class="card-header bg-white fw-bold">Current inventory by warehouse / lot</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Warehouse</th><th>Location</th><th>Lot</th><th>Color / Size</th><th class="text-end">On hand</th><th class="text-end">Reserved</th></tr></thead><tbody>@foreach($balances as $balance)<tr><td>{{ $balance->warehouse_name ?: '-' }}</td><td>{{ $balance->location ?: '-' }}</td><td>{{ $balance->lot_roll_no ?: '-' }}</td><td>{{ $balance->material_color ?: '-' }} / {{ $balance->material_size ?: '-' }}</td><td class="text-end">{{ number_format($balance->balance_qty, 0) }}</td><td class="text-end">{{ number_format($balance->reserved_qty, 0) }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('.move-priority').forEach(button => button.addEventListener('click', () => {
    const row = button.closest('tr');
    const sibling = button.dataset.direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) return;
    if (button.dataset.direction === 'up') row.parentNode.insertBefore(row, sibling);
    else row.parentNode.insertBefore(sibling, row);
    document.querySelectorAll('#priorityRows .priority-value').forEach((input, i) => input.value = (i + 1) * 10);
    document.getElementById('priorityHint').textContent = 'Unsaved order. Save to recalculate the quantities shown.';
}));
document.querySelectorAll('.priority-value').forEach(input => input.addEventListener('input', () => {
    document.getElementById('priorityHint').textContent = 'Unsaved priorities. Save to reorder and recalculate.';
}));
const stockSearch = document.getElementById('stockPlanSearch');
stockSearch.addEventListener('input', () => {
    const term = stockSearch.value.trim().toLocaleLowerCase();
    const rows = [...document.querySelectorAll('[data-plan-search]')];
    let visible = 0;
    rows.forEach(row => {
        row.hidden = !row.dataset.planSearch.toLocaleLowerCase().includes(term);
        if (!row.hidden) visible++;
    });
    document.querySelectorAll('[data-bom-search]').forEach(badge => {
        badge.hidden = !badge.dataset.bomSearch.toLocaleLowerCase().includes(term);
    });
    document.querySelectorAll('.move-priority').forEach(button => button.disabled = !!term);
    document.getElementById('stockPlanSearchHint').textContent = term
        ? `${visible} matching orders. Clear search to move rows with arrows. Quantities still include all orders.`
        : 'Filtering does not change the allocation order or calculated quantities.';
});
</script>
@endpush
