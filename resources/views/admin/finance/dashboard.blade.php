@extends('layouts.app')
@section('title', 'Finance Dashboard')
@section('content')

<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Finance & Costing Dashboard</h5>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="month" name="month" class="form-control form-control-sm" style="width:180px" value="{{ $month }}" onchange="this.form.submit()">
        </form>
    </div>

    <!-- KPI Cards Row 1 -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <small>Total Output</small>
                    <h3 class="mb-0">{{ number_format($summary->total_output) }}</h3>
                    <small>pcs / {{ $summary->working_days }} days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <small>Avg Daily Output</small>
                    <h3 class="mb-0">{{ number_format($summary->avg_daily_output) }}</h3>
                    <small>pcs/day</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body">
                    <small>Achievement</small>
                    <h3 class="mb-0">{{ $summary->achievement_pct }}%</h3>
                    <small>vs plan: {{ number_format($summary->planned_qty) }} pcs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body">
                    <small>Working Days</small>
                    <h3 class="mb-0">{{ $summary->working_days }}</h3>
                    <small>days this month</small>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards Row 2 - Financial -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Total Revenue</small>
                    <h4 class="mb-0 text-success">{{ number_format($summary->total_revenue, 0) }} đ</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Total Cost (Actual)</small>
                    <h4 class="mb-0 text-danger">{{ number_format($summary->total_actual_cost, 0) }} đ</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Gross Profit</small>
                    <h4 class="mb-0 {{ $summary->total_profit >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($summary->total_profit, 0) }} đ
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Profit Margin</small>
                    <h4 class="mb-0 {{ $summary->profit_margin >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $summary->profit_margin }}%
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Daily Trend -->
        <div class="col-md-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2"></i>Daily Production Trend ({{ $month }})</h6>
                </div>
                <div class="card-body">
                    <div style="height:250px; display:flex; align-items:end; gap:2px;">
                        @php $maxQty = $dailyTrend->max('qty') ?: 1; @endphp
                        @foreach($dailyTrend as $d)
                            <div style="flex:1; display:flex; flex-direction:column; align-items:center;">
                                <div style="width:100%; background:#0d6efd; height:{{ ($d->qty/$maxQty)*200 }}px; min-height:2px; border-radius:3px 3px 0 0;"
                                     title="{{ $d->work_date }}: {{ $d->qty }} pcs"></div>
                                <small style="font-size:0.55rem; transform:rotate(-45deg); margin-top:2px; white-space:nowrap;">
                                    {{ \Carbon\Carbon::parse($d->work_date)->format('d') }}
                                </small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses by Category -->
        <div class="col-md-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2"></i>Expenses by Category</h6>
                    <a href="{{ route('admin.finance.expenses', ['month' => $month]) }}" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <div class="card-body">
                    @forelse($expenses as $cat => $exp)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <span class="badge bg-{{ $cat=='labor'?'primary':($cat=='overhead'?'warning':($cat=='utility'?'info':'secondary')) }}">
                                    {{ ucfirst($cat) }}
                                </span>
                            </span>
                            <span class="fw-bold">{{ number_format($exp->total, 0) }} đ</span>
                        </div>
                    @empty
                        <p class="text-muted text-center py-3">No expenses recorded</p>
                    @endforelse
                    @if(count($expenses) > 0)
                        <hr>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total</span>
                            <span>{{ number_format($summary->total_expenses, 0) }} đ</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue by Line -->
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Output by Line</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Line</th><th class="text-end">Output</th><th class="text-end">Share</th></tr>
                </thead>
                <tbody>
                    @php $totalLineQty = $revenueByLine->sum('qty') ?: 1; @endphp
                    @foreach($revenueByLine as $r)
                        <tr>
                            <td>{{ $r->SewingLine }}</td>
                            <td class="text-end fw-bold">{{ number_format($r->qty) }}</td>
                            <td class="text-end">{{ round(($r->qty / $totalLineQty) * 100, 1) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@section('quick-actions')
<div class="d-flex gap-2 mb-3">
    <a href="{{ route('admin.finance.cost-analysis', ['month' => $month]) }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-calculator"></i> Cost Analysis
    </a>
    <a href="{{ route('admin.finance.profitability', ['month' => $month]) }}" class="btn btn-outline-success btn-sm">
        <i class="bi bi-currency-dollar"></i> Profitability
    </a>
    <a href="{{ route('admin.finance.order-costings') }}" class="btn btn-outline-warning btn-sm">
        <i class="bi bi-clipboard-data"></i> Order Variance
    </a>
    <a href="{{ route('admin.finance.fob-costs') }}" class="btn btn-outline-dark btn-sm">
        <i class="bi bi-box-seam"></i> FOB Costs
    </a>
    <a href="{{ route('admin.finance.monthly') }}" class="btn btn-outline-info btn-sm">
        <i class="bi bi-calendar3"></i> Monthly Report
    </a>
</div>
@endsection

@endsection
