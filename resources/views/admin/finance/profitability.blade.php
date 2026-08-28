@extends('layouts.app')
@section('title', 'Profitability Report')
@section('content')

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-currency-dollar me-2"></i>Profitability Report</h5>
            <form method="GET">
                <input type="month" name="month" class="form-control form-control-sm" style="width:160px" value="{{ $month }}" onchange="this.form.submit()">
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <small>Total Revenue</small>
                    <h4 class="mb-0">{{ number_format($summary->total_revenue, 0) }} đ</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body">
                    <small>Total Actual Cost</small>
                    <h4 class="mb-0">{{ number_format($summary->total_actual_cost, 0) }} đ</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm {{ $summary->total_profit >= 0 ? 'bg-primary' : 'bg-danger' }} text-white">
                <div class="card-body">
                    <small>Total Profit</small>
                    <h4 class="mb-0">{{ number_format($summary->total_profit, 0) }} đ</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body">
                    <small>Avg Margin</small>
                    <h4 class="mb-0">{{ number_format($summary->avg_margin, 1) }}%</h4>
                    <small>Best: {{ $summary->best_style }} | Worst: {{ $summary->worst_style }}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Profitability by Style</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Style</th>
                        <th>Customer</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Revenue/Unit</th>
                        <th class="text-end">Cost/Unit</th>
                        <th class="text-end">Total Revenue</th>
                        <th class="text-end">Total Cost</th>
                        <th class="text-end">Profit</th>
                        <th>Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report as $r)
                        <tr>
                            <td class="fw-bold">{{ $r->style_no }}</td>
                            <td><small>{{ $r->customer }}</small></td>
                            <td class="text-end">{{ number_format($r->total_qty) }}</td>
                            <td class="text-end">{{ number_format($r->revenue_per_unit, 0) }} đ</td>
                            <td class="text-end">{{ number_format($r->actual_total_cost, 0) }} đ</td>
                            <td class="text-end text-success">{{ number_format($r->total_revenue, 0) }} đ</td>
                            <td class="text-end text-danger">{{ number_format($r->total_actual_cost, 0) }} đ</td>
                            <td class="text-end fw-bold {{ $r->total_profit >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($r->total_profit, 0) }} đ
                            </td>
                            <td>
                                @php
                                    $mc = $r->profit_margin_percent >= 20 ? 'success' : ($r->profit_margin_percent >= 0 ? 'warning' : 'danger');
                                @endphp
                                <span class="badge bg-{{ $mc }}">
                                    {{ number_format($r->profit_margin_percent, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">No data. Run Cost Analysis first.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
