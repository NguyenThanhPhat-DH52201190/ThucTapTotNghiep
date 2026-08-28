@extends('layouts.app')
@section('title', 'Production Order - ' . $order->cu)
@section('content')

@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}</div>
    @endif

    <!-- Order Info -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-info-circle me-2"></i>
                {{ $order->cu }} - {{ ucfirst($order->operation) }}
                <span class="badge bg-{{ $order->status == 'completed' ? 'success' : ($order->status == 'in_progress' ? 'info' : 'secondary') }} ms-2">
                    {{ str_replace('_', ' ', ucfirst($order->status)) }}
                </span>
            </h5>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.shopfloor.index') }}" class="btn btn-sm btn-secondary">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <small class="text-muted d-block">Style</small>
                    <strong>{{ $order->Style ?? '-' }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">PO</small>
                    <strong>{{ $order->PO ?? '-' }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Line</small>
                    <span class="badge" style="background:{{ $order->LineColor ?? '#808080' }};">{{ $order->line }}</span>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Operation</small>
                    <strong>{{ ucfirst($order->operation) }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Planned Qty</small>
                    <strong>{{ number_format($order->planned_qty) }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Actual / Defects</small>
                    <strong>{{ number_format($totalActual) }}</strong> / <span class="text-danger">{{ $totalDefects }}</span>
                </div>
            </div>
            @if($order->mtp_id)
            <div class="mt-3 p-2 bg-light rounded">
                <small>
                    <strong>MTP Plan:</strong>
                    Cut: {{ $order->planned_cut_start ?? '?' }} → {{ $order->planned_cut_end ?? '?' }}
                    | Sew: {{ $order->planned_sew_start ?? '?' }} → {{ $order->planned_sew_end ?? '?' }}
                    @if($order->actual_cut_start) | ✅ Actual Cut: {{ $order->actual_cut_start }} → {{ $order->actual_cut_end ?? '...' }} @endif
                    @if($order->actual_sew_start) | ✅ Actual Sew: {{ $order->actual_sew_start }} → {{ $order->actual_sew_end ?? '...' }} @endif
                </small>
            </div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <!-- Daily Entry Form -->
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Enter Daily Production</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.shopfloor.daily.store') }}">
                        @csrf
                        <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                        <div class="mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="production_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Qty</label>
                            <input type="number" name="target_qty" class="form-control" value="{{ $order->daily_target_qty ?? $order->planned_qty }}">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Actual Qty <span class="text-danger">*</span></label>
                                <input type="number" name="actual_qty" class="form-control" required min="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Defect Qty</label>
                                <input type="number" name="defect_qty" class="form-control" min="0" value="0">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Downtime (minutes)</label>
                                <input type="number" name="downtime_minutes" class="form-control" min="0" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Operators</label>
                                <input type="number" name="operator_count" class="form-control" min="0" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daily Entries History -->
        <div class="col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Daily Production History</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Target</th>
                                <th>Actual</th>
                                <th>Defects</th>
                                <th>Downtime</th>
                                <th>Efficiency</th>
                                <th>Operators</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dailyEntries as $entry)
                                <tr>
                                    <td>{{ $entry->production_date }}</td>
                                    <td>{{ $entry->target_qty }}</td>
                                    <td class="fw-bold">{{ $entry->actual_qty }}</td>
                                    <td class="text-danger">{{ $entry->defect_qty }}</td>
                                    <td>{{ $entry->downtime_minutes }}m</td>
                                    <td>
                                        @if($entry->efficiency > 0)
                                            <span class="badge bg-{{ $entry->efficiency >= 80 ? 'success' : ($entry->efficiency >= 60 ? 'warning' : 'danger') }}">
                                                {{ $entry->efficiency }}%
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $entry->operator_count }}</td>
                                    <td><small>{{ $entry->notes ?? '' }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-3 text-muted">No entries yet</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td>TOTAL</td>
                                <td>{{ $dailyEntries->sum('target_qty') }}</td>
                                <td>{{ $totalActual }}</td>
                                <td class="text-danger">{{ $totalDefects }}</td>
                                <td>{{ $totalDowntime }}m</td>
                                <td></td>
                                <td>{{ $totalOperators }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
