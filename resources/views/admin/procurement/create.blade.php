@extends('layouts.app')
@section('title', 'Create Purchase Order')
@section('content')

<div class="container-fluid px-0">
    <form method="POST" action="{{ route('admin.procurement.store') }}" id="poForm">
        @csrf

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus me-2"></i>Create Purchase Order</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} - {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order Date</label>
                        <input type="date" name="order_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expected Delivery</label>
                        <input type="date" name="expected_delivery" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
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
                            <th>Unit Price</th>
                            <th>Total</th>
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
                            <td id="totalAmount">0 đ</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.procurement.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Create PO</button>
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
            <td><input type="text" name="items[${i}][material_code]" class="form-control form-control-sm" required value="${data.code || ''}"></td>
            <td><input type="text" name="items[${i}][material_name]" class="form-control form-control-sm" required value="${data.name || ''}"></td>
            <td>
                <select name="items[${i}][unit]" class="form-select form-select-sm">
                    <option value="M">M</option><option value="YD">YD</option><option value="KG">KG</option>
                    <option value="PCS">PCS</option><option value="SET">SET</option><option value="ROLL">ROLL</option>
                </select>
            </td>
            <td><input type="number" step="0.01" name="items[${i}][quantity]" class="form-control form-control-sm qty" required min="0.01" value="${data.qty || ''}" onchange="calcTotal()"></td>
            <td><input type="number" step="0.01" name="items[${i}][unit_price]" class="form-control form-control-sm price" value="${data.price || ''}" onchange="calcTotal()"></td>
            <td class="row-total text-end">0</td>
            <td><input type="date" name="items[${i}][expected_date]" class="form-control form-control-sm" value="${data.date || ''}"></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(${i})"><i class="bi bi-x"></i></button></td>
        </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', html);
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
        row.querySelector('.row-total').textContent = total.toLocaleString('vi-VN', {minimumFractionDigits: 0});
        totalQty += qty;
        totalAmt += total;
    });
    document.getElementById('totalQty').textContent = totalQty.toFixed(2);
    document.getElementById('totalAmount').textContent = totalAmt.toLocaleString('vi-VN', {minimumFractionDigits: 0}) + ' đ';
}
</script>
@endsection
