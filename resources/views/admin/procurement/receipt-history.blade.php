@extends('layouts.app')
@section('title', 'Receipt History - ' . $receipt->receipt_number)
@section('content')
@php
    $user = auth()->user();
    $canTrackPo = $user->role === 'admin' || ($user->role === 'ppic' && in_array($user->ppic_team, ['track', 'both'], true));
@endphp
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-box-seam me-2"></i>Receipt History</h5>
                <div class="text-muted small">{{ $receipt->receipt_number }} · PO {{ $po->po_number }}</div>
            </div>
            <div class="d-flex gap-2">
                @if($canTrackPo)<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editReceiptHistory">Edit receipt data</button>@endif
                <a href="{{ route('admin.procurement.show', $po->id) }}" class="btn btn-sm btn-secondary">Back to PO</a>
            </div>
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

    @if($canTrackPo)
    <div class="collapse mb-4 {{ $errors->any() ? 'show' : '' }}" id="editReceiptHistory">
        <form method="POST" action="{{ route('admin.procurement.receipts.update', [$po->id, $receipt->id]) }}" class="card shadow-sm border-0">
            @csrf @method('PATCH')
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Edit receipt data</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Receipt date</label><input class="form-control" type="date" name="received_date" value="{{ old('received_date', $receipt->received_date) }}" required></div>
                    <div class="col-md-3"><label class="form-label">Declaration date</label><input class="form-control" type="date" name="customs_declaration_date" value="{{ old('customs_declaration_date', $receipt->customs_declaration_date) }}"></div>
                    <div class="col-md-3"><label class="form-label">Declaration No.</label><input class="form-control" name="customs_declaration_number" maxlength="100" value="{{ old('customs_declaration_number', $receipt->customs_declaration_number) }}"></div>
                    <div class="col-md-3"><label class="form-label">Contract No.</label><input class="form-control" name="contract_number" maxlength="100" value="{{ old('contract_number', $receipt->contract_number) }}"></div>
                    <div class="col-md-4"><label class="form-label">Reference</label><input class="form-control" name="reference_number" maxlength="191" value="{{ old('reference_number', $receipt->reference_number) }}"></div>
                    <div class="col-md-8"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="1" maxlength="5000">{{ old('notes', $receipt->notes) }}</textarea></div>
                </div>
                <hr>
                <h6 class="fw-bold mb-3">Customs item data</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light"><tr><th>PO material code</th><th>Lot No</th><th>Roll No</th><th>Customs material code</th><th>Customs unit price</th><th class="text-end">Received qty</th></tr></thead>
                        <tbody>
                            @foreach($items as $index => $item)
                            <tr>
                                <td>{{ $item->material_code }}</td><td>{{ $item->lot_no ?: '-' }}</td><td>{{ $item->roll_no ?: '-' }}</td>
                                <td><input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}"><input class="form-control form-control-sm" name="items[{{ $index }}][customs_material_code]" maxlength="100" value="{{ old("items.$index.customs_material_code", $item->customs_material_code) }}"></td>
                                <td><input class="form-control form-control-sm text-end" type="number" min="0" step="0.0001" name="items[{{ $index }}][customs_unit_price]" value="{{ old("items.$index.customs_unit_price", $item->customs_unit_price) }}"></td>
                                <td class="text-end">{{ number_format((float) $item->quantity_received, 4) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primary">Save changes</button></div>
        </form>
    </div>
    @endif

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
