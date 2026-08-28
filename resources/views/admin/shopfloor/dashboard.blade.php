@extends('layouts.app')
@section('title', 'Shop Floor Dashboard')
@section('content')

@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}</div>
    @endif

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Cutting</h6>
                            <h3 class="mb-0">{{ $dailySummary['cut']->total_qty ?? 0 }}</h3>
                        </div>
                        <i class="bi bi-scissors fs-1 opacity-50"></i>
                    </div>
                    <small>Defects: {{ $dailySummary['cut']->total_defects ?? 0 }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Sewing</h6>
                            <h3 class="mb-0">{{ $dailySummary['sewing']->total_qty ?? 0 }}</h3>
                        </div>
                        <i class="bi bi-gear fs-1 opacity-50"></i>
                    </div>
                    <small>Defects: {{ $dailySummary['sewing']->total_defects ?? 0 }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Finishing</h6>
                            <h3 class="mb-0">{{ $dailySummary['finishing']->total_qty ?? 0 }}</h3>
                        </div>
                        <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    </div>
                    <small>Defects: {{ $dailySummary['finishing']->total_defects ?? 0 }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Downtime</h6>
                            <h3 class="mb-0">
                                {{ ($dailySummary['cut']->total_downtime ?? 0) + ($dailySummary['sewing']->total_downtime ?? 0) + ($dailySummary['finishing']->total_downtime ?? 0) }}
                            </h3>
                        </div>
                        <i class="bi bi-clock fs-1 opacity-50"></i>
                    </div>
                    <small>minutes today</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-palette2 me-2"></i>Color / Size Reconciliation</h5></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>CS</th><th>Style</th><th>Order color</th><th>Produced color</th><th>Size</th><th class="text-end">Order qty</th><th class="text-end">Produced today</th></tr></thead><tbody>
            @forelse($colorSizeSummary as $summary)<tr><td>{{ $summary->CS }}</td><td>{{ $summary->SNo }}</td><td>{{ $summary->order_color }}</td><td>{{ $summary->color ?: '-' }}</td><td>{{ $summary->size_name ?: '-' }}</td><td class="text-end">{{ $summary->ordered_qty === null ? '-' : number_format($summary->ordered_qty) }}</td><td class="text-end fw-bold">{{ number_format($summary->produced_qty) }}</td></tr>
            @empty<tr><td colspan="7" class="text-center py-3 text-muted">No color/size detail logged for this date.</td></tr>@endforelse
        </tbody></table></div>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="{{ $date }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Line</label>
                    <select name="line" class="form-select">
                        <option value="">All Lines</option>
                        @foreach($lines as $l)
                            <option value="{{ $l->name }}" {{ $line == $l->name ? 'selected' : '' }}>{{ $l->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Apply</button>
                    <a href="{{ route('admin.shopfloor.dashboard') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Production Orders -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Active Production Orders (WIP)</h5>
            <div>
                <a href="{{ route('admin.shopfloor.wip') }}" class="btn btn-outline-primary btn-sm me-1">
                    <i class="bi bi-clipboard-data"></i> WIP Report
                </a>
                <a href="{{ route('admin.shopfloor.efficiency') }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-graph-up"></i> Efficiency
                </a>
                <a href="{{ route('admin.shopfloor.mps-logs') }}" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-person-workspace"></i> MPS Logs &amp; Actual Labor
                </a>
                <a href="{{ route('admin.shopfloor.downtime') }}" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-stopwatch"></i> Downtime
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>CU</th>
                        <th>Style</th>
                        <th>Line</th>
                        <th>Operation</th>
                        <th>Planned</th>
                        <th>Actual</th>
                        <th>Defects</th>
                        <th>WIP</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeOrders as $order)
                        <tr>
                            <td class="fw-semibold">{{ $order->cu }}</td>
                            <td>
                                <small>{{ $order->Style ?? '' }}</small>
                                @if($order->StyleName)
                                    <br><small class="text-muted">{{ $order->StyleName }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge" style="background:{{ $order->LineColor ?? '#808080' }}; color:#fff;">
                                    {{ $order->line }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $opLabel = match($order->operation) {
                                        'cut' => '✂ Cutting',
                                        'sewing' => '🧵 Sewing',
                                        'finishing' => '✅ Finishing',
                                        default => $order->operation
                                    };
                                @endphp
                                {{ $opLabel }}
                            </td>
                            <td>{{ number_format($order->planned_qty) }}</td>
                            <td class="fw-bold">{{ number_format($order->actual_qty) }}</td>
                            <td class="text-danger">{{ number_format($order->defect_qty) }}</td>
                            <td>{{ number_format($order->wip_qty) }}</td>
                            <td>
                                @if($order->planned_qty > 0)
                                    @php $pct = min(100, round(($order->actual_qty / $order->planned_qty) * 100)); @endphp
                                    <div class="progress" style="height:8px;">
                                        <div class="progress-bar bg-{{ $pct >= 100 ? 'success' : ($pct >= 50 ? 'info' : 'warning') }}"
                                             style="width:{{ $pct }}%"></div>
                                    </div>
                                    <small>{{ $pct }}%</small>
                                @else
                                    <small>-</small>
                                @endif
                            </td>
                            <td>
                                @php
                                    $stColor = match($order->status) {
                                        'in_progress' => 'info',
                                        'completed' => 'success',
                                        'on_hold' => 'danger',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge bg-{{ $stColor }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.shopfloor.show', $order->id) }}" class="btn btn-sm btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No active production orders. Create from Master Plan first.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
