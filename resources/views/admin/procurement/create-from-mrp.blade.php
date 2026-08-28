@extends('layouts.app')
@section('title', 'Create PO from MRP')
@section('content')

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus me-2"></i>Create Purchase Order from MRP: {{ $mrp->mrp_code }}</h5>
        </div>
        <div class="card-body">
            <p>Total materials needed: <strong>{{ $items->count() }}</strong> | Total cost: <strong>{{ number_format($items->sum(function($i) { return $i->planned_order_qty * 0; }), 0) }} đ</strong></p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.procurement.store') }}">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
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
                            <th style="width:40px"><input type="checkbox" checked onchange="document.querySelectorAll('.item-cb').forEach(c=>c.checked=this.checked)"></th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Unit</th>
                            <th>Net Req.</th>
                            <th>Planned Order</th>
                            <th>Recommended Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $i => $item)
                        <tr>
                            <td><input type="checkbox" class="item-cb" checked onchange="updateVisibility()"></td>
                            <td><code>{{ $item->material_code }}</code></td>
                            <td><small>{{ $item->material_name }}</small></td>
                            <td><span class="badge bg-info">{{ $item->material_type }}</span></td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end">{{ number_format($item->net_requirement, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->planned_order_qty, 2) }}</td>
                            <td>
                                <input type="number" step="0.01" name="items[{{ $i }}][quantity]" class="form-control form-control-sm item-qty"
                                       value="{{ ceil($item->planned_order_qty) }}" min="0.01" style="width:120px" required>
                                <input type="hidden" name="items[{{ $i }}][material_code]" value="{{ $item->material_code }}">
                                <input type="hidden" name="items[{{ $i }}][material_name]" value="{{ $item->material_name }}">
                                <input type="hidden" name="items[{{ $i }}][unit]" value="{{ $item->unit }}">
                                <input type="hidden" name="items[{{ $i }}][material_id]" value="{{ $item->material_id }}">
                                <input type="hidden" name="items[{{ $i }}][mrp_suggestion_id]" value="{{ $suggestions[$item->material_id]->id ?? '' }}">
                            </td>
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
function updateVisibility() {
    document.querySelectorAll('.item-cb').forEach((cb, i) => {
        const row = cb.closest('tr');
        row.querySelector('.item-qty').disabled = !cb.checked;
    });
}
</script>
@endsection
