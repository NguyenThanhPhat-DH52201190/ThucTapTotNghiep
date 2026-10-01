@extends('layouts.app')
@section('title', 'Receipt History - ' . $receipt->receipt_number)
@section('content')
<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-box-seam me-2"></i>Receipt History</h5>
                <div class="text-muted small">{{ $receipt->receipt_number }} · PO {{ $po->po_number }}</div>
            </div>
            <a href="{{ route('admin.procurement.show', $po->id) }}" class="btn btn-sm btn-secondary">Back to PO</a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Supplier</small><strong>{{ $po->supplier_code }} - {{ $po->supplier_name }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Receipt date</small><strong>{{ $receipt->received_date }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Declaration date</small><strong>{{ $receipt->customs_declaration_date ?? '-' }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Declaration No.</small><strong>{{ $receipt->customs_declaration_number ?? '-' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Contract No.</small><strong>{{ $receipt->contract_number ?? '-' }}</strong></div>
                <div class="col-md-4"><small class="text-muted d-block">Reference</small><strong>{{ $receipt->reference_number ?? '-' }}</strong></div>
                <div class="col-md-8"><small class="text-muted d-block">Notes</small><strong>{{ $receipt->notes ?: '-' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">Received items ({{ $items->count() }})</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>PO material code</th><th>Lot No</th><th>Roll No</th>
                        <th>Customs material code</th><th class="text-end">Customs unit price</th>
                        <th class="text-end">Received qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>{{ $item->material_code ?? '-' }}</td>
                        <td>{{ $item->lot_no ?: '-' }}</td>
                        <td>{{ $item->roll_no ?: '-' }}</td>
                        <td>{{ $item->customs_material_code ?: '-' }}</td>
                        <td class="text-end">{{ $item->customs_unit_price !== null ? number_format((float) $item->customs_unit_price, 4) : '-' }}</td>
                        <td class="text-end">{{ number_format((float) $item->quantity_received, 4) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No item details for this receipt.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
