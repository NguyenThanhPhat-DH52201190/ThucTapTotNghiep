@extends('layouts.app')
@section('title', 'MPS Production & Actual Labor')
@section('content')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><h5 class="mb-0">Record production and actual labor</h5></div>
        <div class="card-body">
            @if($schedules->isEmpty())
                <div class="text-muted">No released or in-production MPS schedules are available.</div>
            @else
                <form method="POST" action="{{ route('admin.shopfloor.mps-log.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-4"><label class="form-label">MPS schedule</label><select name="mps_schedule_id" class="form-select" required><option value="">Select schedule</option>@foreach($schedules as $schedule)<option value="{{ $schedule->id }}" @selected(old('mps_schedule_id') == $schedule->id)>{{ $schedule->wo_number }} | {{ $schedule->CS }} | {{ $schedule->line_name }} | SMV {{ $schedule->smv }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="log_date" class="form-control" value="{{ old('log_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                    <div class="col-md-2"><label class="form-label">Stage</label><select name="operation_stage" class="form-select" required><option value="cut">Cutting</option><option value="sewing" selected>Sewing</option><option value="finishing">Finishing</option></select></div>
                    <div class="col-md-2"><label class="form-label">Target qty</label><input type="number" name="target_qty" min="0" class="form-control" value="{{ old('target_qty', 0) }}" required></div>
                    <div class="col-md-2"><label class="form-label">Actual qty</label><input type="number" name="actual_qty" min="0" class="form-control" value="{{ old('actual_qty', 0) }}" required></div>
                    <div class="col-md-2"><label class="form-label">Defects</label><input type="number" name="defect_qty" min="0" class="form-control" value="{{ old('defect_qty', 0) }}"></div>
                    <div class="col-md-2"><label class="form-label">Workers</label><input type="number" name="actual_workers" min="0" class="form-control" value="{{ old('actual_workers', 0) }}"></div>
                    <div class="col-md-2"><label class="form-label">Working hours</label><input type="number" name="working_hours" min="0" step="0.25" class="form-control" value="{{ old('working_hours', 0) }}"></div>
                    <div class="col-md-2"><label class="form-label">Labor rate / hour</label><input type="number" name="labor_rate" min="0" step="0.01" class="form-control" value="{{ old('labor_rate', 0) }}"></div>
                    <div class="col-12"><div class="border rounded p-3"><div class="d-flex justify-content-between align-items-center mb-2"><strong>Color / size output detail</strong><button type="button" class="btn btn-sm btn-outline-primary" id="add-detail">Add detail</button></div><div id="detail-rows"></div><small class="text-muted">If details are entered, their total must equal good quantity (actual minus defects).</small></div></div>
                    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-save"></i> Save production log</button></div>
                </form>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0">Actual labor history</h5><form method="GET"><select name="schedule_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All schedules</option>@foreach($schedules as $schedule)<option value="{{ $schedule->id }}" @selected(request('schedule_id') == $schedule->id)>{{ $schedule->wo_number }} | {{ $schedule->CS }}</option>@endforeach</select></form></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>WO / CS</th><th>Line</th><th>Stage</th><th class="text-end">Good qty</th><th class="text-end">Workers</th><th class="text-end">Hours</th><th class="text-end">SMV</th><th class="text-end">Efficiency</th><th class="text-end">Labor cost</th></tr></thead><tbody>
            @forelse($logs as $log)
                @php($minutes = $log->actual_workers * $log->working_hours * 60)
                @php($efficiency = $minutes > 0 ? (($log->actual_qty - $log->defect_qty) * $log->smv / $minutes) * 100 : 0)
                <tr><td>{{ \Carbon\Carbon::parse($log->log_date)->format('d/m/Y') }}</td><td>{{ $log->wo_number }} / {{ $log->CS }}@if(($detailsByLog[$log->id] ?? collect())->isNotEmpty())<br><small class="text-muted">{{ $detailsByLog[$log->id]->map(fn($detail) => trim(($detail->color ?? '') . ' ' . ($detail->size_name ?? '')) . ': ' . $detail->quantity)->implode(', ') }}</small>@endif</td><td>{{ $log->line_name }}</td><td>{{ ucfirst($log->operation_stage) }}</td><td class="text-end">{{ number_format($log->actual_qty - $log->defect_qty) }}</td><td class="text-end">{{ $log->actual_workers }}</td><td class="text-end">{{ number_format($log->working_hours, 2) }}</td><td class="text-end">{{ number_format($log->smv, 2) }}</td><td class="text-end">{{ number_format($efficiency, 1) }}%</td><td class="text-end fw-bold">{{ number_format($log->actual_labor_cost, 0) }}</td></tr>
            @empty
                <tr><td colspan="10" class="text-center py-3 text-muted">No MPS production logs yet.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="card-footer">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
@push('scripts')
<script>
let detailIndex = 0;
document.getElementById('add-detail')?.addEventListener('click', () => {
    document.getElementById('detail-rows').insertAdjacentHTML('beforeend', `<div class="row g-2 mb-2"><div class="col-md-4"><input name="details[${detailIndex}][color]" class="form-control" placeholder="Color"></div><div class="col-md-4"><input name="details[${detailIndex}][size_name]" class="form-control" placeholder="Size"></div><div class="col-md-3"><input name="details[${detailIndex}][quantity]" type="number" min="1" class="form-control" placeholder="Good qty"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" onclick="this.closest('.row').remove()">&times;</button></div></div>`);
    detailIndex++;
});
</script>
@endpush
