@extends('layouts.app')
@section('title', 'New MRP Calculation')
@section('content')

<div class="container-fluid px-0">
    <form method="POST" action="{{ route('admin.mrp.calculate') }}">
        @csrf

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-calculator me-2"></i>MRP Calculation Parameters</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Period From</label>
                        <input type="date" name="period_from" class="form-control" value="{{ old('period_from', now()->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Period To</label>
                        <input type="date" name="period_to" class="form-control" value="{{ old('period_to', now()->addMonth()->format('Y-m-d')) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Select MTP Records</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAll(true)">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Deselect All</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)"></th>
                            <th>CU</th>
                            <th>Line</th>
                            <th>Style</th>
                            <th>Qty_dis</th>
                            <th>Status</th>
                            <th>BOM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mtpRecords as $mtp)
                            <tr>
                                <td><input type="checkbox" name="selected_mtp[]" value="{{ $mtp->id }}" checked class="mtp-checkbox"></td>
                                <td class="fw-semibold">{{ $mtp->CU }}</td>
                                <td>{{ $mtp->Line }}</td>
                                <td><small>{{ $mtp->Style }}</small></td>
                                <td class="fw-bold">{{ number_format($mtp->Qty_dis) }}</td>
                                <td><span class="badge bg-info">{{ $mtp->mps_status }}</span></td>
                                <td>
                                    @if($mtp->bom_header_id)
                                        <span class="badge bg-success">Has BOM</span>
                                    @else
                                        <span class="badge bg-danger">No BOM</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-3 text-muted">No MTP records with Qty_dis > 0</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.mrp.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-calculator me-1"></i> Calculate MRP
            </button>
        </div>
    </form>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.mtp-checkbox').forEach(cb => cb.checked = checked);
    document.getElementById('selectAll').checked = checked;
}
</script>
@endsection
