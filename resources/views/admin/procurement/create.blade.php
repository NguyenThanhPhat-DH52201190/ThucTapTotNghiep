@extends('layouts.app')
@section('title', isset($po) ? 'Edit Purchase Order' : 'Create Purchase Order')
@section('content')

<div class="container-fluid px-0">
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
                <table class="table table-bordered mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Material Code</th>
                            <th>Name</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            <th>Unit Price (USD)</th>
                            <th>Total (USD)</th>
                            <th>Expected Date</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">GRAND TOTAL:</td>
                            <td id="totalQty">0</td>
                            <td></td>
                            <td id="totalAmount">0.0000 USD</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.procurement.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> {{ isset($po) ? 'Update PO' : 'Create PO' }}</button>
        </div>
    </form>
</div>

<script>
let rowCount = 0;
function addRow(data = {}) {
    rowCount++;
    const i = rowCount;
    const html = `
        <tr id="row${i}">
            <td>
                <select name="items[${i}][material_code]" class="form-select form-select-sm material-code" required onchange="materialChanged(this)"></select>
                <input type="hidden" name="items[${i}][material_id]" class="material-id" value="${data.materialId || data.material_id || ''}">
                <input type="hidden" name="items[${i}][mrp_suggestion_id]" value="${data.suggestionId || data.mrp_suggestion_id || ''}">
            </td>
            <td><input type="text" name="items[${i}][material_name]" class="form-control form-control-sm material-name" required readonly value="${data.name || data.material_name || ''}"></td>
            <td>
                <select name="items[${i}][unit]" class="form-select form-select-sm">
                    <option value="M">M</option><option value="YD">YD</option><option value="KG">KG</option>
                    <option value="PCS">PCS</option><option value="SET">SET</option><option value="ROLL">ROLL</option>
                </select>
            </td>
            <td><input type="number" step="0.01" name="items[${i}][quantity]" class="form-control form-control-sm qty" required min="0.01" value="${data.qty || data.quantity || ''}" onchange="calcTotal()"></td>
            <td><input type="number" step="0.0001" min="0" name="items[${i}][unit_price]" class="form-control form-control-sm price" value="${data.price || data.unit_price || ''}" placeholder="0.0000" onchange="calcTotal()"></td>
            <td class="row-total text-end">0.0000 USD</td>
            <td><input type="date" name="items[${i}][expected_date]" class="form-control form-control-sm" value="${data.date || data.expected_date || ''}"></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(${i})"><i class="bi bi-x"></i></button></td>
        </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', html);
    document.querySelector(`#row${i} select[name$="[unit]"]`).value = data.unit || 'M';
    populateMaterialSelect(document.querySelector(`#row${i} .material-code`), data.materialId || data.material_id || '');
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
        const option = new Option(`${material.internal_code} — ${material.material_name}`, material.internal_code);
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
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty')?.value || 0);
        const price = parseFloat(row.querySelector('.price')?.value || 0);
        const total = qty * price;
        row.querySelector('.row-total').textContent = total.toLocaleString('en-US', {minimumFractionDigits: 4, maximumFractionDigits: 4}) + ' USD';
        totalQty += qty;
        totalAmt += total;
    });
    document.getElementById('totalQty').textContent = totalQty.toFixed(2);
    document.getElementById('totalAmount').textContent = totalAmt.toLocaleString('en-US', {minimumFractionDigits: 4, maximumFractionDigits: 4}) + ' USD';
}

@php
    $initialPoItems = old('items');
    if ($initialPoItems === null) {
        $initialPoItems = isset($items) ? $items->map(function ($item) {
            return [
                'code' => $item->material_code,
                'name' => $item->material_name,
                'unit' => $item->unit,
                'qty' => $item->quantity,
                'price' => number_format((float) $item->unit_price, 4, '.', ''),
                'date' => $item->expected_date,
                'materialId' => $item->material_id,
                'suggestionId' => $item->mrp_suggestion_id,
            ];
        })->values() : [];
    }
@endphp
const initialItems = @json($initialPoItems);
if (initialItems.length) initialItems.forEach(addRow); else addRow();
calcTotal();
</script>
@endsection
