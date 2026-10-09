@extends('layouts.app')
@section('title', isset($po) ? 'Edit Purchase Order' : 'Create Purchase Order')
@section('content')

<div class="container-fluid px-0">
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>PO was not saved. Please fix the following:</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ isset($po) ? route('admin.procurement.update', $po->id) : route('admin.procurement.store') }}" id="poForm">
        @csrf
        @isset($po) @method('PUT') @endisset

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus me-2"></i>{{ isset($po) ? 'Edit Purchase Order' : 'Create Purchase Order' }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="poNumber" class="form-label">PO Number <span class="text-danger">*</span></label>
                        <input id="poNumber" type="text" name="po_number" class="form-control @error('po_number') is-invalid @enderror" value="{{ isset($po) ? $po->po_number : old('po_number') }}" maxlength="50" required @readonly(isset($po))>
                        @error('po_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplierId" class="form-select" required onchange="supplierChanged()">
                            <option value="">-- Select --</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" @selected(old('supplier_id', $po->supplier_id ?? null) == $s->id)>{{ $s->code }} - {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order Date</label>
                        <input type="date" name="order_date" class="form-control" value="{{ old('order_date', $po->order_date ?? now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expected Delivery</label>
                        <input type="date" name="expected_delivery" class="form-control" value="{{ old('expected_delivery', $po->expected_delivery ?? '') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">PO Currency</label>
                        <select name="currency" id="poCurrency" class="form-select" required>
                            <option value="USD" @selected(old('currency', $po->currency ?? 'USD') === 'USD')>USD</option>
                            <option value="VND" @selected(old('currency', $po->currency ?? 'USD') === 'VND')>VND</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Exchange Rate</label>
                        <input type="number" name="exchange_rate" id="exchangeRate" class="form-control @error('exchange_rate') is-invalid @enderror" min="0.000001" step="0.000001" value="{{ old('exchange_rate', $po->exchange_rate ?? '') }}" required>
                        <div class="form-text">Enter the exchange rate for this PO.</div>
                        @error('exchange_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">VAT (%)</label>
                        <input type="number" name="vat_percent" id="vatPercent" class="form-control @error('vat_percent') is-invalid @enderror" min="0" max="100" step="0.01" value="{{ old('vat_percent', $po->vat_percent ?? '0') }}">
                        @error('vat_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $po->notes ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>PO Items</h6>
                <button type="button" class="btn btn-primary btn-sm" onclick="addRow()"><i class="bi bi-plus-lg"></i> Add Item</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 po-create-items-table" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Material Code</th>
                            <th>Name</th>
                            <th>Color</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            <th>Unit Price (PO Currency: <span class="currency-label">{{ old('currency', $po->currency ?? 'USD') }}</span>)</th>
                            <th class="text-end">Total (<span class="currency-label">{{ old('currency', $po->currency ?? 'USD') }}</span>, incl. VAT)</th>
                            <th class="text-end">Exchange Rate</th>
                            <th>Note</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">GRAND TOTAL (incl. VAT):</td>
                            <td id="totalQty">0</td>
                            <td></td>
                            <td id="totalAmount" class="text-end">0.0000 USD</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Surcharges</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addSurchargeRow()"><i class="bi bi-plus-lg"></i> Add Surcharge</button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-light"><tr><th>Description</th><th style="width:130px">Quantity</th><th style="width:110px">Unit</th><th style="width:180px">Unit Price (PO Currency: <span class="currency-label">{{ old('currency', $po->currency ?? 'USD') }}</span>)</th><th style="width:180px">Total (<span class="currency-label">{{ old('currency', $po->currency ?? 'USD') }}</span>, incl. VAT)</th><th style="width:50px"></th></tr></thead>
                    <tbody id="surchargeBody"></tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.procurement.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> {{ isset($po) ? 'Update PO' : 'Create PO' }}</button>
        </div>
    </form>
</div>

<style>
    .po-create-items-table { min-width: 2050px; table-layout: auto; }
    .po-create-items-table th { white-space: nowrap !important; word-break: keep-all; vertical-align: middle; min-width: 110px; }
    .po-create-items-table td { vertical-align: middle; }
    .po-create-items-table th:nth-child(1), .po-create-items-table td:nth-child(1) { min-width: 250px; }
    .po-create-items-table th:nth-child(2), .po-create-items-table td:nth-child(2) { min-width: 240px; }
    .po-create-items-table th:nth-child(3), .po-create-items-table td:nth-child(3) { min-width: 130px; }
    .po-create-items-table th:nth-child(6), .po-create-items-table td:nth-child(6),
    .po-create-items-table th:nth-child(7), .po-create-items-table td:nth-child(7) { min-width: 155px; }
    .po-create-items-table th:nth-child(8), .po-create-items-table td:nth-child(8) { min-width: 145px; }
    .po-create-items-table th:nth-child(9), .po-create-items-table td:nth-child(9) { min-width: 180px; }
    .po-create-items-table th:nth-child(10), .po-create-items-table td:nth-child(10) { min-width: 55px; }
    .po-create-items-table td:nth-child(7) {
        min-width: 135px;
        white-space: nowrap;
    }
</style>

<script>
let rowCount = 0;
let surchargeCount = 0;
function escapeSurchargeValue(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}
function addSurchargeRow(data = {}) {
    const i = surchargeCount++;
    const description = escapeSurchargeValue(data.description || '');
    const quantity = escapeSurchargeValue(data.quantity ?? 0);
    const unit = escapeSurchargeValue(data.unit || 'EA');
    const unitPrice = escapeSurchargeValue(data.unit_price ?? '');
    const html = `<tr id="surcharge${i}">
        <td><input type="text" name="surcharges[${i}][description]" class="form-control form-control-sm surcharge-description" maxlength="191" required value="${description}" placeholder="e.g. Below MOQ weaving surcharge"></td>
        <td><input type="number" name="surcharges[${i}][quantity]" class="form-control form-control-sm surcharge-qty" min="0" step="0.0001" required value="${quantity}"></td>
        <td><input type="text" name="surcharges[${i}][unit]" class="form-control form-control-sm" maxlength="20" required value="${unit}"></td>
        <td><input type="number" name="surcharges[${i}][unit_price]" class="form-control form-control-sm surcharge-price" min="0" step="0.0001" required value="${unitPrice}"></td>
        <td class="surcharge-total text-end">0.0000 USD</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSurchargeRow(${i})" aria-label="Remove surcharge">×</button></td>
    </tr>`;
    document.getElementById('surchargeBody').insertAdjacentHTML('beforeend', html);
    document.querySelectorAll(`#surcharge${i} input`).forEach(input => input.addEventListener('input', calcTotal));
    calcTotal();
}

function removeSurchargeRow(id) {
    document.getElementById(`surcharge${id}`)?.remove();
    calcTotal();
}

function addRow(data = {}) {
    rowCount++;
    const i = rowCount;
    const selectedUnit = String(data.unit || '');
    const unitOptions = @json($units);
    const unitSelectOptions = unitOptions.map(unit => `<option value="${escapeSurchargeValue(unit)}" ${String(unit) === selectedUnit ? 'selected' : ''}>${escapeSurchargeValue(unit)}</option>`).join('');
    const html = `
        <tr id="row${i}">
            <td>
                <select name="items[${i}][material_code]" class="form-select form-select-sm material-code" required onchange="materialChanged(this)"></select>
                <input type="hidden" name="items[${i}][po_item_id]" value="${data.po_item_id || data.id || ''}">
                <input type="hidden" name="items[${i}][material_id]" class="material-id" value="${data.materialId || data.material_id || ''}">
                <input type="hidden" name="items[${i}][mrp_suggestion_id]" value="${data.suggestionId || data.mrp_suggestion_id || ''}">
            </td>
            <td><input type="text" name="items[${i}][material_name]" class="form-control form-control-sm material-name" required readonly value="${data.name || data.material_name || ''}"></td>
            <td><input type="text" class="form-control form-control-sm material-color" readonly value="${escapeSurchargeValue(data.color || '')}" placeholder="-" tabindex="-1"></td>
            <td>
                <select name="items[${i}][unit]" class="form-select form-select-sm">${unitSelectOptions}</select>
            </td>
            <td><input type="number" step="0.01" name="items[${i}][quantity]" class="form-control form-control-sm qty" required min="0.01" value="${data.qty || data.quantity || ''}" onchange="calcTotal()"></td>
                        <td><input type="number" step="0.0001" min="0" name="items[${i}][unit_price]" class="form-control form-control-sm price" value="${data.price || data.unit_price || ''}" placeholder="0.0000" onchange="calcTotal()"></td>
            <td class="row-total text-end fw-semibold">0.0000</td>
            <td class="row-exchange-rate text-end">-</td>
            <td><input type="text" name="items[${i}][notes]" class="form-control form-control-sm" maxlength="1000" value="${escapeSurchargeValue(data.notes || '')}" placeholder="Add note"></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(${i})" ${Number(data.received_qty || 0) > 0 ? 'disabled title="This item has received goods and cannot be removed"' : ''}><i class="bi bi-x"></i></button></td>
        </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', html);
    document.querySelector(`#row${i} select[name$="[unit]"]`).value = selectedUnit;
    populateMaterialSelect(document.querySelector(`#row${i} .material-code`), data.materialId || data.material_id || '');
    if (data.price !== undefined || data.unit_price !== undefined) {
        document.querySelector(`#row${i} .price`).value = data.price ?? data.unit_price ?? '';
        calcTotal();
    }
}

const vendorMaterials = @json($vendorMaterials->groupBy('vendor_id'));

function supplierChanged() {
    document.querySelectorAll('.material-code').forEach(select => populateMaterialSelect(select));
    calcTotal();
}

function populateMaterialSelect(select, selectedMaterialId = '') {
    const supplierId = document.getElementById('supplierId').value;
    const materials = vendorMaterials[supplierId] || [];
    const currentId = String(selectedMaterialId || select.closest('tr').querySelector('.material-id').value || '');
    select.innerHTML = '<option value="">-- Select mapped material --</option>';
    materials.forEach(material => {
        const colorSize = [material.color, material.size].filter(value => value && value.trim() !== '').join(' / ');
        const option = new Option(`${material.internal_code} — ${material.material_name}${colorSize ? ` — ${colorSize}` : ''}`, material.internal_code);
        option.dataset.materialId = material.material_id;
        if (String(material.material_id) === currentId) option.selected = true;
        select.add(option);
    });
    materialChanged(select);
}

function materialChanged(select) {
    const row = select.closest('tr');
    const supplierId = document.getElementById('supplierId').value;
    const selected = (vendorMaterials[supplierId] || []).find(material =>
        String(material.material_id) === String(select.selectedOptions[0]?.dataset.materialId || '')
    );
    row.querySelector('.material-id').value = selected?.material_id || '';
    row.querySelector('.material-name').value = selected?.material_name || '';
    row.querySelector('.material-color').value = selected?.color || '';
    row.querySelector('select[name$="[unit]"]').value = selected?.unit || 'M';
    row.querySelector('.price').value = selected ? Number(selected.unit_price || 0).toFixed(4) : '';
    calcTotal();
}

function removeRow(id) {
    const row = document.getElementById('row' + id);
    if (row && document.querySelectorAll('#itemsBody tr').length > 1) {
        row.remove();
        calcTotal();
    }
}

function calcTotal() {
    let totalQty = 0, totalAmt = 0;
    const currency = document.getElementById('poCurrency')?.value || 'USD';
    const vatRate = parseFloat(document.getElementById('vatPercent')?.value || 0);
    document.querySelectorAll('.currency-label').forEach(label => label.textContent = currency);
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty')?.value || 0);
        const price = parseFloat(row.querySelector('.price')?.value || 0);
        const total = qty * price;
        const totalInclVat = total * (1 + vatRate / 100);
        row.querySelector('.row-total').textContent = totalInclVat.toLocaleString('en-US', {minimumFractionDigits: 4, maximumFractionDigits: 4}) + ' ' + currency;
        row.querySelector('.row-exchange-rate').textContent = document.getElementById('exchangeRate')?.value || '-';
        totalQty += qty;
        totalAmt += total;
    });
    document.querySelectorAll('#surchargeBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.surcharge-qty')?.value || 0);
        const price = parseFloat(row.querySelector('.surcharge-price')?.value || 0);
        const total = qty * price;
        const totalInclVat = total * (1 + vatRate / 100);
        row.querySelector('.surcharge-total').textContent = totalInclVat.toLocaleString('en-US', {minimumFractionDigits: 4, maximumFractionDigits: 4}) + ' ' + currency;
        totalAmt += total;
    });
    document.getElementById('totalQty').textContent = totalQty.toFixed(2);
    const totalInclVat = totalAmt * (1 + vatRate / 100);
    document.getElementById('totalAmount').textContent = totalInclVat.toLocaleString('en-US', {minimumFractionDigits: 4, maximumFractionDigits: 4}) + ' ' + currency;
}

@php
    $initialPoItems = old('items');
    if ($initialPoItems === null) {
        $initialPoItems = isset($items) ? $items->map(function ($item) {
            return [
                'po_item_id' => $item->id,
                'code' => $item->material_code,
                'name' => $item->material_name,
                'unit' => $item->unit,
                'qty' => $item->quantity,
                'received_qty' => $item->received_qty,
                'price' => number_format((float) $item->unit_price, 4, '.', ''),
                'notes' => $item->notes,
                'materialId' => $item->material_id,
                'suggestionId' => $item->mrp_suggestion_id,
            ];
        })->values() : [];
    }
@endphp
const initialItems = @json($initialPoItems);
if (initialItems.length) initialItems.forEach(addRow); else addRow();
@php
    $initialSurcharges = old('surcharges');
    if ($initialSurcharges === null) {
        $initialSurcharges = isset($surcharges) ? $surcharges->map(fn ($row) => [
            'description' => $row->description, 'quantity' => $row->quantity, 'unit' => $row->unit,
            'unit_price' => number_format((float) $row->unit_price, 4, '.', ''),
        ])->values() : [];
    }
@endphp
const initialSurcharges = @json($initialSurcharges);
initialSurcharges.forEach(addSurchargeRow);
document.getElementById('poCurrency')?.addEventListener('change', calcTotal);
document.getElementById('exchangeRate')?.addEventListener('input', calcTotal);
document.getElementById('vatPercent')?.addEventListener('input', calcTotal);
document.querySelectorAll('.price, .qty').forEach(input => input.addEventListener('input', calcTotal));
calcTotal();
</script>
@endsection
