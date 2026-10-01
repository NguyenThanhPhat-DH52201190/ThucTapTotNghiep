@extends('layouts.app')
@section('title', 'PO - ' . $po->po_number)
@section('content')
@php
    $user = auth()->user();
    $canCreatePo = $user->role === 'admin' || ($user->role === 'ppic' && in_array($user->ppic_team, ['create', 'both'], true));
    $canTrackPo = $user->role === 'admin' || ($user->role === 'ppic' && in_array($user->ppic_team, ['track', 'both'], true));
@endphp
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
                @if($canTrackPo)<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#poPdfModal"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>@endif
                @if($canCreatePo)
                    <a href="{{ route('admin.procurement.edit', $po->id) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                @endif
                @if($canCreatePo)
                    <form method="POST" action="{{ route('admin.procurement.destroy', $po->id) }}" class="d-inline" onsubmit="return confirm('Delete this PO?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                @endif
                @if($canTrackPo && $po->status !== 'received' && $po->status !== 'cancelled')
                    @if(in_array($po->status, ['confirmed', 'partial']))
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#receiveModal">Receive goods</button>
                    @endif
                    @if(!in_array($po->status, ['confirmed', 'partial', 'closed']))
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
                @if($user->role === 'admin' && $receipts->isNotEmpty() && !in_array($po->status, ['closed', 'cancelled'], true))
                    <form method="POST" action="{{ route('admin.procurement.close', $po->id) }}" class="d-inline" onsubmit="return confirm('Close this purchase order? Any unreceived quantity will no longer count as open supply. Existing receipts and received stock will be preserved.')">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-lock me-1"></i>Close Order</button>
                    </form>
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
                    @php $sc = match($po->status) { 'sent'=>'info', 'confirmed'=>'primary', 'received'=>'success', 'partial'=>'warning', 'closed'=>'dark', 'cancelled'=>'danger', default=>'secondary' } @endphp
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
                        <th class="text-end">Received</th><th class="text-end">Pending</th><th class="text-end">Unit Price (USD)</th>
                        <th class="text-end">Total (USD)</th><th>Note</th><th>Status</th>
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
                            <td class="text-end fw-semibold {{ (float) $item->quantity - (float) $item->received_qty < 0 ? 'text-danger' : '' }}">{{ number_format((float) $item->quantity - (float) $item->received_qty, 2) }}</td>
                            <td class="text-end">{{ number_format($item->unit_price, 4) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->total_price, 4) }} USD</td>
                            <td><small>{{ $item->notes ?? '-' }}</small></td>
                            <td>
                                @php $sc = match($item->status) { 'partial'=>'warning', 'received'=>'success', 'cancelled'=>'danger', default=>'secondary' } @endphp
                                <span class="badge bg-{{ $sc }}">{{ $item->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                    @foreach($surcharges as $surcharge)
                        <tr class="table-warning"><td><code>Surcharge</code></td><td><small>{{ $surcharge->description }}</small></td><td>{{ $surcharge->unit }}</td><td class="text-end">{{ number_format($surcharge->quantity, 2) }}</td><td class="text-end">-</td><td class="text-end">-</td><td class="text-end">{{ number_format($surcharge->unit_price, 4) }}</td><td class="text-end fw-bold">{{ number_format($surcharge->total_price, 4) }} USD</td><td>-</td><td>-</td></tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="7" class="text-end">TOTAL:</td>
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
        <div class="table-responsive" id="receipt-history">
            <table class="table table-sm mb-0 receipt-history-table">
                <thead class="table-light">
                    <tr><th>Receipt#</th><th>Receipt date</th><th>Declaration date</th><th>Declaration No.</th><th>Contract No.</th><th>Customs material / unit price</th><th>Reference</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    @foreach($receipts as $r)
                        @php($itemsForReceipt = $receiptItems[$r->id] ?? collect())
                        <tr>
                            <td class="fw-bold">{{ $r->receipt_number }}</td>
                            <td>{{ $r->received_date }}</td>
                            <td>{{ $r->customs_declaration_date ?? '-' }}</td>
                            <td>{{ $r->customs_declaration_number ?? '-' }}</td>
                            <td>{{ $r->contract_number ?? '-' }}</td>
                            <td>
                                @if($itemsForReceipt->isNotEmpty())
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#receipt-items-{{ $r->id }}" aria-expanded="false" aria-controls="receipt-items-{{ $r->id }}">View {{ $itemsForReceipt->count() }} item(s)</button>
                                @else
                                    <span class="text-muted">No item details</span>
                                @endif
                            </td>
                            <td><small>{{ $r->reference_number ?? '-' }}</small></td>
                            <td><small>{{ $r->notes ?? '' }}</small></td>
                        </tr>
                        @if($itemsForReceipt->isNotEmpty())
                        <tr class="bg-light">
                            <td colspan="8" class="p-0">
                                <div class="collapse p-3" id="receipt-items-{{ $r->id }}">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 bg-white receipt-item-table">
                                        <thead class="table-light"><tr>
                                            <th>PO material code</th><th>Lot No</th><th>Roll No</th>
                                            <th>Customs material code</th><th class="text-end">Customs unit price</th>
                                            <th class="text-end">Received qty</th>
                                        </tr></thead>
                                        <tbody>
                                            @foreach($itemsForReceipt as $receiptItem)
                                            <tr>
                                                <td>{{ $receiptItem->material_code ?? '-' }}</td>
                                                <td>{{ $receiptItem->lot_no ?: '-' }}</td>
                                                <td>{{ $receiptItem->roll_no ?: '-' }}</td>
                                                <td>{{ $receiptItem->customs_material_code ?: '-' }}</td>
                                                <td class="text-end">{{ $receiptItem->customs_unit_price !== null ? number_format((float) $receiptItem->customs_unit_price, 4) : '-' }}</td>
                                                <td class="text-end">{{ number_format((float) $receiptItem->quantity_received, 4) }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<style>
    #receipt-history .receipt-history-table { min-width: 1450px; table-layout: fixed; }
    #receipt-history .receipt-history-table th { white-space: nowrap; vertical-align: middle; }
    #receipt-history .receipt-history-table td { vertical-align: middle; }
    #receipt-history .receipt-history-table th:nth-child(1), #receipt-history .receipt-history-table td:nth-child(1) { min-width: 260px; }
    #receipt-history .receipt-history-table th:nth-child(2), #receipt-history .receipt-history-table td:nth-child(2) { min-width: 120px; }
    #receipt-history .receipt-history-table th:nth-child(3), #receipt-history .receipt-history-table td:nth-child(3) { min-width: 150px; }
    #receipt-history .receipt-history-table th:nth-child(4), #receipt-history .receipt-history-table td:nth-child(4) { min-width: 150px; }
    #receipt-history .receipt-history-table th:nth-child(5), #receipt-history .receipt-history-table td:nth-child(5) { min-width: 140px; }
    #receipt-history .receipt-history-table th:nth-child(6), #receipt-history .receipt-history-table td:nth-child(6) { min-width: 250px; }
    #receipt-history .receipt-history-table th:nth-child(7), #receipt-history .receipt-history-table td:nth-child(7) { min-width: 180px; }
    #receipt-history .receipt-history-table th:nth-child(8), #receipt-history .receipt-history-table td:nth-child(8) { min-width: 220px; }
    #receipt-history .receipt-item-table { min-width: 1100px; table-layout: fixed; }
    #receipt-history .receipt-item-table th { white-space: nowrap; vertical-align: middle; }
    #receipt-history .receipt-item-table td { white-space: nowrap; }
    #receipt-history .receipt-item-table th:nth-child(1), #receipt-history .receipt-item-table td:nth-child(1) { min-width: 220px; }
    #receipt-history .receipt-item-table th:nth-child(2), #receipt-history .receipt-item-table td:nth-child(2),
    #receipt-history .receipt-item-table th:nth-child(3), #receipt-history .receipt-item-table td:nth-child(3) { min-width: 140px; }
    #receipt-history .receipt-item-table th:nth-child(4), #receipt-history .receipt-item-table td:nth-child(4) { min-width: 240px; }
    #receipt-history .receipt-item-table th:nth-child(5), #receipt-history .receipt-item-table td:nth-child(5) { min-width: 180px; }
    #receipt-history .receipt-item-table th:nth-child(6), #receipt-history .receipt-item-table td:nth-child(6) { min-width: 150px; }
</style>

@if($canTrackPo && in_array($po->status, ['confirmed', 'partial']))
<style>
    #receiveModal .modal-dialog { max-width: min(96vw, 1600px); }
    #receiveModal .receipt-lines { overflow-x: auto; }
    #receiveModal .receipt-lines table { min-width: 1450px; table-layout: fixed; }
    #receiveModal .receipt-lines th { white-space: nowrap; }
    #receiveModal .receipt-lines td { vertical-align: middle; }
    #receiveModal .receipt-lines input.form-control { min-width: 0 !important; width: 100%; }
    #receiveModal .receipt-lines .receipt-material { width: 230px; overflow-wrap: anywhere; }
    #receiveModal .receipt-lines .receipt-remaining { width: 100px; white-space: nowrap; }
    #receiveModal .receipt-lines .receipt-actions { width: 110px; }
    #receiveModal .receipt-lines th:nth-child(1), #receiveModal .receipt-lines td:nth-child(1) { width: 230px; }
    #receiveModal .receipt-lines th:nth-child(2), #receiveModal .receipt-lines td:nth-child(2) { width: 100px; }
    #receiveModal .receipt-lines th:nth-child(3), #receiveModal .receipt-lines td:nth-child(3) { width: 120px; }
    #receiveModal .receipt-lines th:nth-child(4), #receiveModal .receipt-lines td:nth-child(4) { width: 100px; }
    #receiveModal .receipt-lines th:nth-child(5), #receiveModal .receipt-lines td:nth-child(5),
    #receiveModal .receipt-lines th:nth-child(6), #receiveModal .receipt-lines td:nth-child(6) { width: 130px; }
    #receiveModal .receipt-lines th:nth-child(7), #receiveModal .receipt-lines td:nth-child(7) { width: 180px; }
    #receiveModal .receipt-lines th:nth-child(8), #receiveModal .receipt-lines td:nth-child(8) { width: 150px; }
    #receiveModal .receipt-lines th:nth-child(9), #receiveModal .receipt-lines td:nth-child(9) { width: 130px; }
    #receiveModal .receipt-lines th:nth-child(10), #receiveModal .receipt-lines td:nth-child(10) { width: 110px; white-space: nowrap; }
</style>
<div class="modal fade" id="receiveModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form id="receiveForm" method="POST" action="{{ route('admin.procurement.receipts.store', $po->id) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Receive goods</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="border rounded p-3 mb-3 bg-light">
            <label for="receiptImportFile" class="form-label fw-semibold">Import receipt rows from Excel</label>
            <div class="d-flex flex-wrap gap-2 align-items-center"><input id="receiptImportFile" type="file" class="form-control" accept=".xlsx,.xls,.csv" style="max-width:420px"><button id="importReceiptFile" type="button" class="btn btn-outline-primary">Import</button></div>
            <div class="form-text">Column order can vary. Header row must include Material Code (or Code) and Quantity (or Qty). Optional headers: Size, Lot No, Roll No, or Lot/Roll.</div>
            <div id="receiptImportFeedback" class="small mt-2" role="status"></div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><label class="form-label">Receipt date</label><input type="date" name="received_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
            <div class="col-md-3"><label class="form-label">Customs declaration date</label><input type="date" name="customs_declaration_date" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Customs declaration number</label><input name="customs_declaration_number" class="form-control" maxlength="100"></div>
            <div class="col-md-3"><label class="form-label">Contract number</label><input name="contract_number" class="form-control" maxlength="100"></div>
            <div class="col-md-4"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select" required><option value="">Select warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Delivery reference</label><input name="reference_number" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Location</label><select name="location_id" class="form-select" required><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}">{{ $location->location_code }}{{ $location->location_name ? ' — '.$location->location_name : '' }}</option>@endforeach</select><small class="text-muted">Location must belong to the selected warehouse.</small></div>
        </div>
        <div class="receipt-lines"><table class="table table-sm align-middle"><thead><tr><th>Material</th><th>Remaining</th><th>Color</th><th>Size</th><th>Lot No</th><th>Roll No</th><th>Customs material code</th><th>Customs unit price</th><th>Receive qty</th><th></th></tr></thead><tbody id="receiptRows">
        @foreach($items as $item)
            @if($item->quantity > $item->received_qty)
            <tr data-po-item="{{ $item->id }}">
                <td>{{ $item->material_code }} - {{ $item->material_name }}<input type="hidden" data-field="po_item_id" value="{{ $item->id }}"></td>
                <td>{{ number_format($item->quantity - $item->received_qty, 0) }}</td>
                <td><input class="form-control" style="min-width:100px" value="{{ $item->default_material_color }}" readonly aria-label="Material color"></td>
                <td><input data-field="material_size" class="form-control" style="min-width:80px" value="{{ $item->default_material_size }}" required></td>
                <td><input data-field="lot_no" class="form-control" style="min-width:110px" maxlength="40" placeholder="Lot No" required></td>
                <td><input data-field="roll_no" class="form-control" style="min-width:110px" maxlength="40" placeholder="Roll No" required></td>
                <td><input data-field="customs_material_code" class="form-control" style="min-width:120px" maxlength="100" placeholder="Customs material code"></td>
                <td><input data-field="customs_unit_price" type="number" step="0.0001" min="0" class="form-control" style="min-width:120px" placeholder="0.0000"></td>
                <td><input data-field="quantity" type="number" step="0.0001" min="0.0001" class="form-control" style="min-width:110px" required></td>
                <td><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary add-receipt-row">Add</button><button type="button" class="btn btn-sm btn-outline-danger remove-receipt-row" aria-label="Remove receipt row">×</button></div></td>
            </tr>
            @endif
        @endforeach
        </tbody></table></div>
        <div id="receiptRowError" class="text-danger small mb-2" role="alert"></div><label class="form-label">Notes</label><textarea name="notes" class="form-control"></textarea>
    </div><div class="modal-footer"><button class="btn btn-primary">Post receipt</button></div>
</form></div></div>
@endif

@if($canTrackPo && in_array($po->status, ['draft', 'sent']))
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
    const importButton = document.getElementById('importReceiptFile');
    const importFile = document.getElementById('receiptImportFile');
    const importFeedback = document.getElementById('receiptImportFeedback');
    const previousItems = @json(old('items', []));
    const previousMeta = @json(old());
    if (Object.keys(previousItems).length) {
        const templates = new Map([...rows.children].map(row => [row.dataset.poItem, row.cloneNode(true)]));
        const restored = [];
        Object.values(previousItems).forEach(entry => {
            const template = templates.get(String(entry.po_item_id));
            if (!template) return;
            const copy = template.cloneNode(true);
            ['material_size', 'lot_no', 'roll_no', 'quantity', 'customs_material_code', 'customs_unit_price'].forEach(field => {
                copy.querySelector(`[data-field="${field}"]`).value = entry[field] ?? '';
            });
            restored.push(copy);
        });
        if (restored.length) rows.replaceChildren(...restored);
        ['received_date', 'customs_declaration_date', 'customs_declaration_number', 'contract_number', 'warehouse_id', 'location_id', 'reference_number', 'notes'].forEach(name => {
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
            ['quantity', 'roll_no', 'customs_material_code', 'customs_unit_price'].forEach(field => copy.querySelector(`[data-field="${field}"]`).value = '');
            row.after(copy);
            renumber();
            copy.querySelector('[data-field="roll_no"]').focus();
        } else if (event.target.closest('.remove-receipt-row')) {
            if (rows.children.length === 1) { error.textContent = 'Keep at least one receipt row.'; return; }
            row.remove(); renumber();
        }
    });
    importButton.addEventListener('click', async () => {
        const file = importFile.files[0];
        if (!file) { importFeedback.className = 'small mt-2 text-danger'; importFeedback.textContent = 'Choose an Excel or CSV file first.'; return; }
        importButton.disabled = true;
        importFeedback.className = 'small mt-2 text-muted';
        importFeedback.textContent = 'Reading file…';
        const body = new FormData();
        body.append('file', file);
        try {
            const response = await fetch(@json(route('admin.procurement.receipts.import', $po->id)), {
                method: 'POST', headers: {'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value, 'Accept': 'application/json'}, body
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' '));
            const templates = new Map([...rows.children].map(row => [String(row.dataset.poItem), row.cloneNode(true)]));
            const importedRows = [];
            result.rows.forEach(entry => {
                const template = templates.get(String(entry.po_item_id));
                if (!template) return;
                const copy = template.cloneNode(true);
                copy.querySelector('[data-field="material_size"]').value = entry.material_size;
                copy.querySelector('[data-field="lot_no"]').value = entry.lot_no;
                copy.querySelector('[data-field="roll_no"]').value = entry.roll_no;
                copy.querySelector('[data-field="quantity"]').value = entry.quantity;
                importedRows.push(copy);
            });
            rows.replaceChildren(...importedRows);
            renumber();
            const notices = [`Imported ${importedRows.length} row(s). Review the rows, then click Post receipt.`];
            if (result.errors.length) notices.push(...result.errors);
            if (result.truncated) notices.push('Only the first 2,000 data rows were processed.');
            importFeedback.className = `small mt-2 ${result.errors.length ? 'text-warning' : 'text-success'}`;
            importFeedback.replaceChildren(...notices.map(message => { const line = document.createElement('div'); line.textContent = message; return line; }));
        } catch (failure) {
            importFeedback.className = 'small mt-2 text-danger';
            importFeedback.textContent = failure.message || 'Import failed.';
        } finally { importButton.disabled = false; }
    });
    renumber();
})();
</script>
@endpush
