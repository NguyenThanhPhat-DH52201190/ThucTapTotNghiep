@extends('layouts.app')
@section('title', 'PO - ' . $po->po_number)
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp
@include('admin.procurement.partials.pdf-modal')

<div class="container-fluid px-0">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>{{ $po->po_number }}</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#poPdfModal"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
                @if($canManage && !in_array($po->status, ['partial', 'received']))
                    <a href="{{ route('admin.procurement.edit', $po->id) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                    <form method="POST" action="{{ route('admin.procurement.destroy', $po->id) }}" class="d-inline" onsubmit="return confirm('Delete this PO?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                @endif
                @if($canManage && $po->status !== 'received' && $po->status !== 'cancelled')
                    @if(in_array($po->status, ['confirmed', 'partial']))
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#receiveModal">Receive goods</button>
                    @endif
                    @if(!in_array($po->status, ['confirmed', 'partial']))
                    <form method="POST" action="{{ route('admin.procurement.status', $po->id) }}" class="d-inline">
                        @csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
                            <option value="draft" {{ $po->status=='draft'?'selected':'' }}>📄 Draft</option>
                            <option value="sent" {{ $po->status=='sent'?'selected':'' }}>📨 Sent</option>
                            <option value="confirmed" {{ $po->status=='confirmed'?'selected':'' }}>✅ Confirmed</option>
                            <option value="partial" {{ $po->status=='partial'?'selected':'' }}>📦 Partial</option>
                            <option value="received" {{ $po->status=='received'?'selected':'' }}>✔ Received</option>
                            <option value="cancelled" {{ $po->status=='cancelled'?'selected':'' }}>❌ Cancelled</option>
                        </select>
                    </form>
                    @endif
                @endif
                <a href="{{ route('admin.procurement.index') }}" class="btn btn-sm btn-secondary">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Supplier</small><strong>{{ $po->supplier_code }} - {{ $po->supplier_name }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Contact</small><strong>{{ $po->contact_person ?? 'N/A' }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Phone</small><strong>{{ $po->phone ?? 'N/A' }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Order Date</small><strong>{{ $po->order_date }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Expected</small><strong>{{ $po->expected_delivery ?? 'N/A' }}</strong></div>
                <div class="col-md-1">
                    <small class="text-muted d-block">Status</small>
                    @php $sc = match($po->status) { 'sent'=>'info', 'confirmed'=>'primary', 'received'=>'success', 'partial'=>'warning', 'cancelled'=>'danger', default=>'secondary' } @endphp
                    <span class="badge bg-{{ $sc }}">{{ ucfirst($po->status) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>PO Items</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th><th>Name</th><th>Unit</th><th class="text-end">Qty</th>
                        <th class="text-end">Received</th><th class="text-end">Unit Price (USD)</th>
                        <th class="text-end">Total (USD)</th><th>Expected</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td><code>{{ $item->material_code }}</code></td>
                            <td><small>{{ $item->material_name }}</small></td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end">{{ number_format($item->quantity, 2) }}</td>
                            <td class="text-end">{{ number_format($item->received_qty, 2) }}</td>
                            <td class="text-end">{{ number_format($item->unit_price, 4) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->total_price, 4) }} USD</td>
                            <td><small>{{ $item->expected_date ?? '-' }}</small></td>
                            <td>
                                @php $sc = match($item->status) { 'partial'=>'warning', 'received'=>'success', 'cancelled'=>'danger', default=>'secondary' } @endphp
                                <span class="badge bg-{{ $sc }}">{{ $item->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="6" class="text-end">TOTAL:</td>
                        <td class="text-end text-primary">{{ number_format($po->total_amount, 4) }} USD</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Receipts -->
    @if(count($receipts) > 0)
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Receipt History</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Receipt#</th><th>Date</th><th>Reference</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    @foreach($receipts as $r)
                        <tr>
                            <td class="fw-bold">{{ $r->receipt_number }}</td>
                            <td>{{ $r->received_date }}</td>
                            <td><small>{{ $r->reference_number ?? '-' }}</small></td>
                            <td><small>{{ $r->notes ?? '' }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@if($canManage && in_array($po->status, ['confirmed', 'partial']))
<div class="modal fade" id="receiveModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form id="receiveForm" method="POST" action="{{ route('admin.procurement.receipts.store', $po->id) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Receive goods</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3 mb-3"><div class="col-md-3"><label class="form-label">Receipt date</label><input type="date" name="received_date" class="form-control" value="{{ now()->toDateString() }}" required></div><div class="col-md-4"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select" required><option value="">Select warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label">Delivery reference</label><input name="reference_number" class="form-control"></div><div class="col-md-6"><label class="form-label">Location</label><select name="location_id" class="form-select" required><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}">{{ $location->location_code }}{{ $location->location_name ? ' — '.$location->location_name : '' }}</option>@endforeach</select><small class="text-muted">Location must belong to the selected warehouse.</small></div></div>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Material</th><th>Remaining</th><th>Color</th><th>Size</th><th>Lot No</th><th>Roll No</th><th>Receive qty</th><th></th></tr></thead><tbody id="receiptRows">
        @foreach($items as $item)
            @if($item->quantity > $item->received_qty)
            <tr data-po-item="{{ $item->id }}" data-remaining="{{ $item->quantity - $item->received_qty }}">
                <td>{{ $item->material_code }} - {{ $item->material_name }}<input type="hidden" data-field="po_item_id" value="{{ $item->id }}"></td>
                <td>{{ number_format($item->quantity - $item->received_qty, 0) }}</td>
                <td><input class="form-control" style="min-width:100px" value="{{ $item->default_material_color }}" readonly aria-label="Material color"></td>
                <td><input data-field="material_size" class="form-control" style="min-width:80px" value="{{ $item->default_material_size }}" required></td>
                <td><input data-field="lot_no" class="form-control" style="min-width:110px" maxlength="40" placeholder="Lot No" required></td>
                <td><input data-field="roll_no" class="form-control" style="min-width:110px" maxlength="40" placeholder="Roll No" required></td>
                <td><input data-field="quantity" type="number" step="0.0001" min="0.0001" max="{{ $item->quantity - $item->received_qty }}" class="form-control" style="min-width:110px" required></td>
                <td><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary add-receipt-row">Add</button><button type="button" class="btn btn-sm btn-outline-danger remove-receipt-row" aria-label="Remove receipt row">×</button></div></td>
            </tr>
            @endif
        @endforeach
        </tbody></table></div>
        <div id="receiptRowError" class="text-danger small mb-2" role="alert"></div><label class="form-label">Notes</label><textarea name="notes" class="form-control"></textarea>
    </div><div class="modal-footer"><button class="btn btn-primary">Post receipt</button></div>
</form></div></div>
@endif

@if($canManage && in_array($po->status, ['draft', 'sent']))
@php($allowedStatusTransitions = $po->status === 'draft' ? ['draft', 'sent', 'cancelled'] : ['sent', 'confirmed', 'cancelled'])
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.querySelector('select[name="status"]');
    const allowed = @json($allowedStatusTransitions);
    select?.querySelectorAll('option').forEach(option => {
        if (!allowed.includes(option.value)) option.remove();
    });
});
</script>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('receiveForm');
    if (!form) return;
    const rows = document.getElementById('receiptRows');
    const error = document.getElementById('receiptRowError');
    const previousItems = @json(old('items', []));
    const previousMeta = @json(old());
    if (Object.keys(previousItems).length) {
        const templates = new Map([...rows.children].map(row => [row.dataset.poItem, row.cloneNode(true)]));
        const restored = [];
        Object.values(previousItems).forEach(entry => {
            const template = templates.get(String(entry.po_item_id));
            if (!template) return;
            const copy = template.cloneNode(true);
            ['material_size', 'lot_no', 'roll_no', 'quantity'].forEach(field => {
                copy.querySelector(`[data-field="${field}"]`).value = entry[field] ?? '';
            });
            restored.push(copy);
        });
        if (restored.length) rows.replaceChildren(...restored);
        ['received_date', 'warehouse_id', 'location_id', 'reference_number', 'notes'].forEach(name => {
            if (previousMeta[name] != null) form.elements[name].value = previousMeta[name];
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('receiveModal')).show();
    }
    const warehouse = form.elements.warehouse_id;
    const location = form.elements.location_id;
    function filterLocations() {
        [...location.options].forEach(option => {
            if (!option.value) return;
            option.hidden = option.dataset.warehouse !== warehouse.value;
            option.disabled = option.hidden;
        });
        if (location.selectedOptions[0]?.disabled) location.value = '';
    }
    warehouse.addEventListener('change', filterLocations);
    filterLocations();
    function renumber() {
        [...rows.children].forEach((row, index) => row.querySelectorAll('[data-field]').forEach(input => input.name = `items[${index}][${input.dataset.field}]`));
    }
    rows.addEventListener('click', event => {
        const row = event.target.closest('tr');
        if (event.target.closest('.add-receipt-row')) {
            const copy = row.cloneNode(true);
            ['quantity', 'roll_no'].forEach(field => copy.querySelector(`[data-field="${field}"]`).value = '');
            row.after(copy);
            renumber();
            copy.querySelector('[data-field="roll_no"]').focus();
        } else if (event.target.closest('.remove-receipt-row')) {
            if (rows.children.length === 1) { error.textContent = 'Keep at least one receipt row.'; return; }
            row.remove(); renumber();
        }
    });
    form.addEventListener('submit', event => {
        const totals = {};
        error.textContent = '';
        rows.querySelectorAll('tr').forEach(row => {
            const id = row.dataset.poItem;
            totals[id] = (totals[id] || 0) + Math.round(Number(row.querySelector('[data-field="quantity"]').value) * 10000);
            if (totals[id] > Math.round(Number(row.dataset.remaining) * 10000)) error.textContent = 'Total receive quantity for a material exceeds its remaining quantity.';
        });
        if (error.textContent) event.preventDefault();
    });
    renumber();
})();
</script>
@endpush
