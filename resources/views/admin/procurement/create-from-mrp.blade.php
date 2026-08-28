@extends('layouts.app')
@section('title', 'Create PO from MRP')
@section('content')

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus me-2"></i>Create Purchase Order from MRP: {{ $mrp->mrp_code }}</h5>
        </div>
        <div class="card-body">
            <p class="mb-0">Total materials needed: <strong>{{ $items->count() }}</strong> | Estimated total: <strong id="estimatedTotal">0 đ</strong></p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.procurement.store') }}">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplierId" class="form-select" required>
                            <option value="">-- Select --</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} - {{ $s->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Unit price is loaded from the Material–Supplier mapping; you may adjust it for this PO.</small>
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
                        <textarea name="notes" class="form-control" rows="2" placeholder="Notes...">Created from MRP: {{ $mrp->mrp_code }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>MRP Items to Order</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="toggleAll" checked></th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Unit</th>
                            <th>Net Req.</th>
                            <th>Planned Order</th>
                            <th>Recommended Qty</th>
                            <th>Unit Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $i => $item)
                        <tr data-material-id="{{ $item->material_id }}">
                            <td><input type="checkbox" class="item-cb" checked></td>
                            <td><code>{{ $item->material_code }}</code></td>
                            <td><small>{{ $item->material_name }}</small></td>
                            <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end">{{ number_format($item->net_requirement, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->planned_order_qty, 2) }}</td>
                            <td>
                                <input type="number" step="0.01" name="items[{{ $i }}][quantity]" class="form-control form-control-sm item-field item-qty"
                                       value="{{ ceil($item->planned_order_qty) }}" min="0.01" style="width:120px" required>
                                <input type="hidden" name="items[{{ $i }}][material_code]" class="item-field" value="{{ $item->material_code }}">
                                <input type="hidden" name="items[{{ $i }}][material_name]" class="item-field" value="{{ $item->material_name }}">
                                <input type="hidden" name="items[{{ $i }}][unit]" class="item-field" value="{{ $item->unit }}">
                                <input type="hidden" name="items[{{ $i }}][material_id]" class="item-field" value="{{ $item->material_id }}">
                                <input type="hidden" name="items[{{ $i }}][mrp_suggestion_id]" class="item-field" value="{{ $suggestions[$item->material_id]->id ?? '' }}">
                            </td>
                            <td><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][unit_price]" class="form-control form-control-sm item-field item-price" placeholder="Set price" required></td>
                            <td class="text-end fw-semibold item-total">0 đ</td>
                        </tr>
                        @endforeach
                    </tbody>
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
const vendorPrices = @json($vendorPrices);

function money(value) {
    return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 0 }) + ' đ';
}

function updateRowTotal(row) {
    const total = Number(row.querySelector('.item-qty').value || 0) * Number(row.querySelector('.item-price').value || 0);
    row.querySelector('.item-total').textContent = money(total);
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('tbody tr[data-material-id]').forEach(row => {
        if (row.querySelector('.item-cb').checked) {
            total += Number(row.querySelector('.item-qty').value || 0) * Number(row.querySelector('.item-price').value || 0);
        }
    });
    const totalElement = document.getElementById('estimatedTotal');
    if (totalElement) totalElement.textContent = money(total);
}

function syncPricesFromSupplier() {
    const supplierId = document.getElementById('supplierId').value;
    document.querySelectorAll('tbody tr[data-material-id]').forEach(row => {
        const mapping = vendorPrices.find(item => String(item.material_id) === row.dataset.materialId && String(item.vendor_id) === supplierId);
        const price = row.querySelector('.item-price');
        price.value = mapping ? mapping.unit_price : '';
        price.placeholder = mapping ? '' : 'No supplier price mapping';
        updateRowTotal(row);
    });
    updateTotal();
}

function updateVisibility() {
    document.querySelectorAll('.item-cb').forEach(cb => {
        const row = cb.closest('tr');
        row.querySelectorAll('.item-field').forEach(field => field.disabled = !cb.checked);
    });
    updateTotal();
}

document.getElementById('supplierId').addEventListener('change', syncPricesFromSupplier);
document.getElementById('toggleAll').addEventListener('change', function () {
    document.querySelectorAll('.item-cb').forEach(cb => cb.checked = this.checked);
    updateVisibility();
});
document.querySelectorAll('.item-cb').forEach(cb => cb.addEventListener('change', updateVisibility));
document.querySelectorAll('.item-qty, .item-price').forEach(input => input.addEventListener('input', () => {
    updateRowTotal(input.closest('tr'));
    updateTotal();
}));
syncPricesFromSupplier();
</script>
@endsection
