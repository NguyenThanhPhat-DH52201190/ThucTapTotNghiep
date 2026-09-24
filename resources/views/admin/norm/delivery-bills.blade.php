@extends('layouts.app')
@section('title', 'NORM - Delivery Bill')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h4>Delivery Bill — {{ $order->CS }}</h4><a href="{{ route('admin.norm.materials.show', $order->id) }}" class="btn btn-outline-secondary">Back to NORM</a></div>
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-warning"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><div class="card-body">
<form method="POST" action="{{ route('admin.norm.delivery-bills.store', $order->id) }}" id="deliveryForm">
    @csrf <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label">No *</label><input name="number" value="{{ old('number') }}" maxlength="50" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Customer *</label><input name="customer" value="{{ old('customer', $order->Customer) }}" maxlength="191" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Day</label><input value="{{ now(config('delivery-bills.timezone'))->format('d/m/Y') }}" class="form-control" readonly><small class="text-muted">Date of confirmation</small></div>
        <div class="col-md-6"><label class="form-label">Address *</label><textarea name="address" maxlength="500" class="form-control" required>{{ old('address') }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Reason *</label><textarea name="reason" maxlength="1000" class="form-control" required>{{ old('reason') }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Shipper *</label><input name="shipper" value="{{ old('shipper') }}" maxlength="191" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Shipper Address</label><input name="shipper_address" value="{{ old('shipper_address') }}" maxlength="500" class="form-control"></div>
    </div>
    <p class="text-muted">Select quantities to issue now. Choose one row per lot/roll; use Add row to split a material across lots. Remaining quantities include previous issues.</p>
    <div id="deliveryRows"></div>
    <button type="button" class="btn btn-outline-primary mb-3" id="addDeliveryRow">+ Add row</button>
    <div class="mb-3"><label class="form-label">Priority override reason</label><textarea name="priority_reason" class="form-control" maxlength="1000" @required($errors->has('priority_reason'))>{{ old('priority_reason') }}</textarea><small class="text-muted">Required if this issue reduces stock allocated to a higher-priority CU in Stock Records.</small></div>
    <div class="alert alert-info">Confirming deducts stock, downloads the Excel bill and returns to NORM. Downloading a saved bill never issues stock again.</div>
    <button class="btn btn-success" @disabled($options->isEmpty() || !in_array($order->status, ['pending', 'confirmed', 'in_production', 'released']))>Export Excel &amp; Confirm Issue</button>
</form>
</div></div>
<h5>Confirmed delivery bills</h5>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>No</th><th>Day</th><th>Priority override reason</th><th>Excel</th></tr></thead><tbody>@forelse($bills as $bill)<tr><td>{{ $bill->number }}</td><td>{{ $bill->issued_on }}</td><td>{{ $bill->priority_reason ?: '—' }}</td><td><a class="btn btn-sm btn-outline-success" href="{{ route('admin.norm.delivery-bills.download', [$order->id, $bill->id]) }}">Download again</a></td></tr>@empty<tr><td colspan="4">No confirmed delivery bills.</td></tr>@endforelse</tbody></table></div>
{{ $bills->links() }}
<template id="deliveryRowTemplate"><div class="mb-3 delivery-row">
    <div class="delivery-row-fields">
        <div class="delivery-material"><label class="form-label">Material *</label><select data-field="bom_item_id" class="form-select" required></select></div>
        <div class="delivery-balance"><label class="form-label">Warehouse / Location / Lot / Roll *</label><select data-field="balance_id" class="form-select" required></select></div>
        <div><label class="form-label">Qty *</label><input data-field="quantity" type="number" step="0.0001" min="0.0001" max="99999999" class="form-control" required></div>
        <button type="button" data-remove class="btn btn-outline-danger">Remove</button>
    </div>
    <small class="text-muted delivery-remaining" data-remaining></small>
</div></template>
@endsection
@push('scripts')
<script>
(() => {
    const options = @json($options), initial = @json(old('items', [[]]));
    const box = document.getElementById('deliveryRows'), add = document.getElementById('addDeliveryRow');
    function reindex() {
        Array.from(box.children).forEach((row, i) => {
            row.querySelectorAll('[data-field]').forEach(el => el.name = `items[${i}][${el.dataset.field}]`);
            row.querySelector('[data-remove]').disabled = box.children.length === 1;
        });
        add.disabled = box.children.length >= 100;
    }
    function addRow(data = {}) {
        const row = document.getElementById('deliveryRowTemplate').content.firstElementChild.cloneNode(true);
        const material = row.querySelector('[data-field="bom_item_id"]'), balance = row.querySelector('[data-field="balance_id"]');
        material.add(new Option('Select material', ''));
        options.forEach(o => material.add(new Option(o.label, o.id)));
        material.value = data.bom_item_id || '';
        function refresh() {
            const selected = options.find(o => String(o.id) === material.value);
            balance.replaceChildren(new Option('Select lot / roll', ''));
            (selected?.balances || []).forEach(b => balance.add(new Option(b.label, b.id)));
            row.querySelector('[data-remaining]').textContent = selected ? `Remaining NORM (reference only): ${selected.remaining}. Additional quantities are allowed if stock is available.` : '';
        }
        refresh(); balance.value = data.balance_id || '';
        row.querySelector('[data-field="quantity"]').value = data.quantity || '';
        material.addEventListener('change', refresh);
        row.querySelector('[data-remove]').addEventListener('click', () => { if (box.children.length > 1) { row.remove(); reindex(); } });
        box.append(row); reindex();
    }
    initial.forEach(addRow);
    add.addEventListener('click', () => { if (box.children.length < 100) addRow(); });
    const form = document.getElementById('deliveryForm');
    const submit = form.querySelector('button:not([type="button"])');
    const feedback = document.createElement('div');
    feedback.className = 'alert alert-danger';
    feedback.hidden = true;
    form.prepend(feedback);
    let submitting = false;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        submitting = true;
        submit.disabled = true;
        const label = submit.textContent;
        submit.textContent = 'Confirming and downloading…';
        feedback.hidden = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const contentType = response.headers.get('Content-Type') || '';
            if (!response.ok || contentType.includes('application/json')) {
                const data = contentType.includes('application/json') ? await response.json() : {};
                if (data.errors?.priority_reason) form.elements.priority_reason.required = true;
                throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Unable to download the bill. Check saved bills or retry this form.'));
            }
            if (!response.headers.get('Content-Disposition')?.includes('attachment')) {
                throw new Error('Your session may have expired. Check saved bills before creating another issue.');
            }
            const file = await response.blob();
            const url = URL.createObjectURL(file);
            const link = document.createElement('a');
            link.href = url;
            link.download = response.headers.get('Content-Disposition').match(/filename="?([^";]+)"?/)?.[1] || 'Delivery-Bill.xlsx';
            document.body.append(link);
            link.click();
            link.remove();
            // Let the browser start the local blob download before leaving the form.
            setTimeout(() => window.location.assign(@json(route('admin.norm.materials.show', $order->id))), 500);
        } catch (error) {
            feedback.textContent = error.message || 'Unable to download the bill. Retry this form or check saved bills.';
            feedback.style.whiteSpace = 'pre-line';
            feedback.hidden = false;
            feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
            submitting = false;
            submit.disabled = false;
            submit.textContent = label;
        }
    });
})();
</script>
@endpush
