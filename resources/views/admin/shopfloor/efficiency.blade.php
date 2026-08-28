@extends('layouts.app')
@section('title', 'Efficiency Report')
@section('content')

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-graph-up me-2"></i>Production Efficiency Report</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" class="form-control" value="{{ $month }}">
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> View</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Line</th>
                        <th>Operation</th>
                        <th>Qty</th>
                        <th>Defects</th>
                        <th>Defect %</th>
                        <th>Downtime</th>
                        <th>Avg Efficiency</th>
                        <th>Operators</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $grandQty = 0; $grandDefects = 0; $grandDowntime = 0; $grandEff = 0; $effCount = 0;
                    @endphp
                    @forelse($report as $r)
                        @php
                            $grandQty += $r->total_qty;
                            $grandDefects += $r->total_defects;
                            $grandDowntime += $r->total_downtime;
                            if ($r->avg_efficiency > 0) { $grandEff += $r->avg_efficiency; $effCount++; }
                            $defectPct = $r->total_qty > 0 ? round(($r->total_defects / $r->total_qty) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td>{{ $r->production_date }}</td>
                            <td><span class="badge bg-secondary">{{ $r->line }}</span></td>
                            <td>{{ ucfirst($r->operation) }}</td>
                            <td class="fw-bold">{{ number_format($r->total_qty) }}</td>
                            <td class="text-danger">{{ $r->total_defects }}</td>
                            <td>
                                <span class="badge bg-{{ $defectPct > 5 ? 'danger' : ($defectPct > 2 ? 'warning' : 'success') }}">
                                    {{ $defectPct }}%
                                </span>
                            </td>
                            <td>{{ $r->total_downtime }}m</td>
                            <td>
                                @if($r->avg_efficiency > 0)
                                    <span class="badge bg-{{ $r->avg_efficiency >= 80 ? 'success' : ($r->avg_efficiency >= 60 ? 'warning' : 'danger') }}">
                                        {{ number_format($r->avg_efficiency, 1) }}%
                                    </span>
                                @endif
                            </td>
                            <td>{{ $r->total_operators }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">No data for this period</td></tr>
                    @endforelse
                </tbody>
                @if(count($report) > 0)
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOTAL</td>
                        <td></td>
                        <td></td>
                        <td>{{ number_format($grandQty) }}</td>
                        <td class="text-danger">{{ $grandDefects }}</td>
                        <td>{{ $grandQty > 0 ? round(($grandDefects/$grandQty)*100,1) : 0 }}%</td>
                        <td>{{ $grandDowntime }}m</td>
                        <td>{{ $effCount > 0 ? number_format($grandEff/$effCount, 1) : 0 }}%</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@endsection
