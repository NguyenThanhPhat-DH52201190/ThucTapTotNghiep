@extends('layouts.app')
@section('title', 'PO - ' . $po->po_number)
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>{{ $po->po_number }}</h5>
            <div class="d-flex gap-2">
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
                        <th class="text-end">Received</th><th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th><th>Expected</th><th>Status</th>
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
                            <td class="text-end">{{ number_format($item->unit_price, 0) }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->total_price, 0) }} đ</td>
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
                        <td class="text-end text-primary">{{ number_format($po->total_amount, 0) }} đ</td>
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
<div class="modal fade" id="receiveModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="POST" action="{{ route('admin.procurement.receipts.store', $po->id) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Receive goods</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3 mb-3"><div class="col-md-3"><label class="form-label">Receipt date</label><input type="date" name="received_date" class="form-control" value="{{ now()->toDateString() }}" required></div><div class="col-md-4"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select" required><option value="">Select warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label">Delivery reference</label><input name="reference_number" class="form-control"></div><div class="col-md-6"><label class="form-label">Location</label><select name="location_id" class="form-select" required><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}">{{ $location->location_code }}{{ $location->location_name ? ' — '.$location->location_name : '' }}</option>@endforeach</select><small class="text-muted">Location must belong to the selected warehouse.</small></div></div>
        <table class="table table-sm"><thead><tr><th>Material</th><th>Remaining</th><th>Color</th><th>Size</th><th>Lot/Roll</th><th>Receive qty</th></tr></thead><tbody>
        @foreach($items as $item)
            @if($item->quantity > $item->received_qty)
            <tr><td>{{ $item->material_code }} - {{ $item->material_name }}<input type="hidden" name="items[{{ $item->id }}][po_item_id]" value="{{ $item->id }}"></td><td>{{ number_format($item->quantity - $item->received_qty, 2) }}</td><td><input name="items[{{ $item->id }}][material_color]" class="form-control" value="{{ $item->color }}" required></td><td><input name="items[{{ $item->id }}][material_size]" class="form-control" value="{{ $item->default_material_size }}" placeholder="e.g. M" required></td><td><input name="items[{{ $item->id }}][lot_roll_no]" class="form-control" placeholder="Lot/Roll" required></td><td><input type="number" step="0.0001" min="0.0001" max="{{ $item->quantity - $item->received_qty }}" name="items[{{ $item->id }}][quantity]" class="form-control" required></td></tr>
            @endif
        @endforeach
        </tbody></table><label class="form-label">Notes</label><textarea name="notes" class="form-control"></textarea>
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
