@extends('layouts.app')
@section('title', 'WIP Report')
@section('content')

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2"></i>Work In Progress (WIP) Report</h5>
            <small class="text-muted">Date: {{ $date }}</small>
        </div>
    </div>

    @forelse($report as $line => $orders)
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-gear me-1"></i> Line: {{ $line }}
                    <span class="badge bg-secondary ms-2">{{ $orders->count() }} orders</span>
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>CU</th>
                            <th>Style</th>
                            <th>Operation</th>
                            <th>Planned</th>
                            <th>Actual</th>
                            <th>Defects</th>
                            <th>WIP</th>
                            <th>Progress</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $o)
                            <tr>
                                <td class="fw-semibold">{{ $o->cu }}</td>
                                <td><small>{{ $o->Style }}</small></td>
                                <td>
                                    @php $opIcon = match($o->operation) { 'cut'=>'✂','sewing'=>'🧵','finishing'=>'✅', default=>'📋' }; @endphp
                                    {{ $opIcon }} {{ ucfirst($o->operation) }}
                                </td>
                                <td>{{ number_format($o->planned_qty) }}</td>
                                <td class="fw-bold">{{ number_format($o->actual_qty) }}</td>
                                <td class="text-danger">{{ $o->defect_qty }}</td>
                                <td>{{ $o->wip_qty }}</td>
                                <td>
                                    @if($o->planned_qty > 0)
                                        @php $pct = min(100, round(($o->actual_qty / $o->planned_qty) * 100)); @endphp
                                        <div class="progress" style="height:6px;width:70px;">
                                            <div class="progress-bar bg-{{ $pct >= 100 ? 'success' : ($pct >= 50 ? 'info' : 'warning') }}"
                                                 style="width:{{ $pct }}%"></div>
                                        </div>
                                        <small>{{ $pct }}%</small>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $sc = match($o->status) { 'in_progress'=>'info', 'completed'=>'success', 'on_hold'=>'danger', default=>'secondary' };
                                    @endphp
                                    <span class="badge bg-{{ $sc }}">{{ str_replace('_',' ',ucfirst($o->status)) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-clipboard-check fs-1 d-block mb-2"></i>
            <p>All production orders are completed! 🎉</p>
        </div>
    @endforelse
</div>

@endsection
