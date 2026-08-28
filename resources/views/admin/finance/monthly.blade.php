@extends('layouts.app')
@section('title', 'Monthly Financial Report')
@section('content')

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2"></i>Monthly Financial Report</h5>
            <form method="GET">
                <select name="year" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
                    @for($y = now()->year - 2; $y <= now()->year; $y++)
                        <option value="{{ $y }}" {{ ($year ?? now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th>Styles</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Std Cost</th>
                        <th class="text-end">Actual Cost</th>
                        <th class="text-end">Gross Profit</th>
                        <th class="text-end">Margin</th>
                        <th class="text-end">Expenses</th>
                        <th class="text-end">Net Profit</th>
                        <th>Net Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthlyData as $m)
                        @php
                            $netMargin = $m->revenue > 0 ? round(($m->net_profit / $m->revenue) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td class="fw-bold">{{ $m->period }}</td>
                            <td>{{ $m->style_count }}</td>
                            <td class="text-end text-success">{{ number_format($m->revenue, 0) }} đ</td>
                            <td class="text-end">{{ number_format($m->standard_cost, 0) }} đ</td>
                            <td class="text-end text-danger">{{ number_format($m->actual_cost, 0) }} đ</td>
                            <td class="text-end fw-bold {{ $m->profit >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($m->profit, 0) }} đ
                            </td>
                            <td>
                                <span class="badge bg-{{ $m->avg_margin >= 20 ? 'success' : ($m->avg_margin >= 0 ? 'warning' : 'danger') }}">
                                    {{ number_format($m->avg_margin, 1) }}%
                                </span>
                            </td>
                            <td class="text-end text-danger">{{ number_format($m->expenses, 0) }} đ</td>
                            <td class="text-end fw-bold {{ $m->net_profit >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($m->net_profit, 0) }} đ
                            </td>
                            <td>
                                <span class="badge bg-{{ $netMargin >= 10 ? 'success' : ($netMargin >= 0 ? 'warning' : 'danger') }}">
                                    {{ number_format($netMargin, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-4 text-muted">No data available</td></tr>
                    @endforelse
                </tbody>
                @if(count($monthlyData) > 0)
                <tfoot class="table-light fw-bold">
                    @php
                        $totRev = $monthlyData->sum('revenue');
                        $totCost = $monthlyData->sum('actual_cost');
                        $totProfit = $monthlyData->sum('profit');
                        $totExp = $monthlyData->sum('expenses');
                        $totNet = $monthlyData->sum('net_profit');
                    @endphp
                    <tr>
                        <td>TOTAL</td>
                        <td>{{ $monthlyData->sum('style_count') }}</td>
                        <td class="text-end text-success">{{ number_format($totRev, 0) }} đ</td>
                        <td class="text-end">{{ number_format($monthlyData->sum('standard_cost'), 0) }} đ</td>
                        <td class="text-end text-danger">{{ number_format($totCost, 0) }} đ</td>
                        <td class="text-end {{ $totProfit >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totProfit, 0) }} đ</td>
                        <td>{{ $totRev > 0 ? number_format(($totProfit/$totRev)*100, 1) : 0 }}%</td>
                        <td class="text-end text-danger">{{ number_format($totExp, 0) }} đ</td>
                        <td class="text-end {{ $totNet >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totNet, 0) }} đ</td>
                        <td>{{ $totRev > 0 ? number_format(($totNet/$totRev)*100, 1) : 0 }}%</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
