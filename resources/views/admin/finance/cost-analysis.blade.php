@extends('layouts.app')
@section('title', 'Cost Analysis')
@section('content')

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-calculator me-2"></i>Cost Analysis</h5>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <input type="month" name="month" class="form-control form-control-sm" style="width:160px" value="{{ $month }}">
                    <input type="text" name="style_no" class="form-control form-control-sm" placeholder="Style No" style="width:150px" value="{{ request('style_no') }}">
                    <button type="submit" class="btn btn-sm btn-dark"><i class="bi bi-search"></i></button>
                    <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                </form>
                <form method="POST" action="{{ route('admin.finance.cost-analysis.calculate') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Calculate cost analysis for {{ $month }}?')">
                        <i class="bi bi-lightning"></i> Calculate
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Style</th>
                        <th>Customer</th>
                        <th>Qty</th>
                        <th class="text-end">Std Cost/Unit</th>
                        <th class="text-end">Actual Cost/Unit</th>
                        <th class="text-end">Variance</th>
                        <th class="text-end">Var %</th>
                        <th class="text-end">Revenue/Unit</th>
                        <th class="text-end">Total Profit</th>
                        <th class="text-end">Margin</th>
                        <th>Period</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($analyses as $a)
                        @php
                            $isProfitable = $a->profit_margin_percent >= 0;
                            $varColor = $a->variance_percent <= 0 ? 'success' : 'danger';
                        @endphp
                        <tr>
                            <td class="fw-bold">{{ $a->style_no }}</td>
                            <td><small>{{ $a->customer }}</small></td>
                            <td>{{ number_format($a->total_qty) }}</td>
                            <td class="text-end">{{ number_format($a->standard_total_cost, 0) }} đ</td>
                            <td class="text-end">{{ number_format($a->actual_total_cost, 0) }} đ</td>
                            <td class="text-end text-{{ $varColor }} fw-bold">
                                {{ $a->cost_variance >= 0 ? '+' : '' }}{{ number_format($a->cost_variance, 0) }}
                            </td>
                            <td>
                                <span class="badge bg-{{ $varColor }}">
                                    {{ $a->variance_percent >= 0 ? '+' : '' }}{{ number_format($a->variance_percent, 1) }}%
                                </span>
                            </td>
                            <td class="text-end">{{ number_format($a->revenue_per_unit, 0) }} đ</td>
                            <td class="text-end fw-bold text-{{ $isProfitable ? 'success' : 'danger' }}">
                                {{ number_format($a->total_profit, 0) }} đ
                            </td>
                            <td>
                                <span class="badge bg-{{ $isProfitable ? ($a->profit_margin_percent >= 20 ? 'success' : 'warning') : 'danger' }}">
                                    {{ number_format($a->profit_margin_percent, 1) }}%
                                </span>
                            </td>
                            <td><small>{{ $a->period }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-4 text-muted">No cost analysis. Click "Calculate" to generate.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $analyses->links() }}</div>
    </div>
</div>
@endsection
