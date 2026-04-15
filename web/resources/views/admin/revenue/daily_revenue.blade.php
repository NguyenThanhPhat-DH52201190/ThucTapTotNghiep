@extends('layouts.app')
@section('title', 'Daily Revenue')
@section('content')
@php
$canManage = auth()->user()->role === 'admin';
@endphp

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

@if(session('success'))
<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif

<form method="GET" action="{{ route('revenue.daily.line') }}" class="row g-3 mb-3">
    <input type="hidden" name="line" value="{{ $line }}">

    <div class="col-md-3">
        <label>Month</label>
        <input type="month" name="month" class="form-control" value="{{ $month }}">
    </div>

    <div class="col-md-5 d-flex align-items-end gap-2 flex-wrap revenue-actions">
        <button type="submit" class="btn btn-dark revenue-action-btn">Apply</button>
        <a href="{{ $canManage ? route('admin.revenue.index', ['month' => $month]) : route('revenue.view', ['month' => $month]) }}" class="btn btn-secondary revenue-action-btn">Back Revenue</a>
    </div>
</form>

<div class="mb-2 p-2 border rounded compact-line-panel">
    <div class="compact-line-wrap">
        <span class="fw-bold">Line</span>
        <div class="line-badge line-badge-inline js-line-color" data-line-color="{{ $revenues->first()->LineColor ?? '#808080' }}">
            {{ $line }}
        </div>
    </div>
</div>

@if($canManage)
<div class="mb-4">
    <h5>Enter the quantity in ({{ $month }})</h5>
    @php
    $distributionTotal = 0;
    $monthTotal = 0;
    $dayTotals = [];

    foreach ($days as $day) {
        $dayTotals[$day] = 0;
    }

    foreach ($revenues as $item) {
        $distributionTotal += (int) ($item->Distribution ?? 0);

        foreach ($days as $day) {
            $rawQty = old('matrix.' . $item->id . '.' . $day, $dailyMatrix[$item->id][$day] ?? '');
            $qty = ($rawQty === '' || $rawQty === null) ? 0 : (int) $rawQty;

            $dayTotals[$day] += $qty;
            $monthTotal += $qty;
        }
    }
    @endphp
    <form method="POST" action="{{ route('revenue.daily.matrix.store') }}">
        @csrf
        <input type="hidden" name="line" value="{{ $line }}">
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle text-center matrix-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="matrix-sticky">CS</th>
                        <th rowspan="2">Distribution</th>
                        <th rowspan="2">Total Month</th>
                        @foreach($days as $day)
                        <th>{{ $day }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($days as $day)
                        <th>{{ $day . '-' . $monthLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($revenues as $item)
                    <tr>
                        <td class="matrix-sticky">{{ $item->CS }}</td>
                        <td>{{ $item->Distribution }}</td>
                        <td>{{ $item->actualout }}</td>
                        @foreach($days as $day)
                        @php
                        $qtyValue = old('matrix.' . $item->id . '.' . $day, $dailyMatrix[$item->id][$day] ?? '');
                        @endphp
                        <td>
                            <input
                                type="number"
                                min="0"
                                class="form-control form-control-sm matrix-input"
                                name="matrix[{{ $item->id }}][{{ $day }}]"
                                value="{{ $qtyValue }}"
                            >
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold table-light">
                        <td class="matrix-sticky">Total</td>
                        <td>{{ $distributionTotal }}</td>
                        <td>{{ $monthTotal }}</td>
                        @foreach($days as $day)
                        <td>{{ $dayTotals[$day] ?? 0 }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>

        <button type="submit" class="btn btn-primary revenue-action-btn">Save</button>
    </form>
</div>
@endif

<table class="table table-bordered table-sm compact-summary-table">
    <colgroup>
        <col class="summary-col-line">
        <col class="summary-col-cs">
        <col class="summary-col-distribution">
        <col class="summary-col-actual">
        <col class="summary-col-cmp">
        <col class="summary-col-plan-revenue">
        <col class="summary-col-amount">
    </colgroup>
    <thead>
        <tr>
            <th>Line</th>
            <th>CS</th>
            <th>Distribution</th>
            <th>Actual Out (Month)</th>
            <th>CMP</th>
            <th>PlanRevenue</th>
            <th>ActualRevenue</th>
        </tr>
    </thead>
    <tbody>
        @forelse($revenues as $item)
        <tr>
            <td class="line-badge js-line-color" data-line-color="{{ $item->LineColor ?? '#808080' }}">{{ $item->SewingLine }}</td>
            <td>{{ $item->CS }}</td>
            <td>{{ $item->Distribution }}</td>
            <td>{{ $item->actualout }}</td>
            <td>{{ $item->cmp }}</td>
            <td>{{ '$' . number_format(((float) $item->planout) * ((float) $item->cmp), 2) }}</td>
            <td>{{ '$' . number_format(((float) $item->actualout) * ((float) $item->cmp), 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="text-center">No revenue rows for this line.</td>
        </tr>
        @endforelse
    </tbody>
    @if(count($revenues))
    <tfoot>
        <tr class="fw-bold table-light">
            <td colspan="3" class="text-end">Total</td>
            <td>{{ $totalQty }}</td>
            <td></td>
            <td>{{ '$' . number_format($totalPlanRevenue, 2) }}</td>
            <td>{{ '$' . number_format($totalAmount, 2) }}</td>
        </tr>
    </tfoot>
    @endif
</table>

<style>
.line-badge {
    border-radius: 4px;
    text-align: center;
    color: #fff;
    font-weight: 500;
}

.line-badge-inline {
    display: inline-block;
    padding: 4px 10px;
    min-width: 84px;
}

.compact-line-panel {
    background-color: #f8f9fa;
}

.compact-line-wrap {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.compact-summary-table {
    width: 100%;
    max-width: 920px;
    table-layout: fixed;
    margin-bottom: 10px;
}

.revenue-action-btn {
    min-height: 40px;
    padding: 0.375rem 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
}

.revenue-actions {
    row-gap: 0.5rem;
}

.compact-summary-table th,
.compact-summary-table td {
    padding: 6px 10px;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.summary-col-line {
    width: 120px;
}

.summary-col-cs {
    width: 180px;
}

.summary-col-distribution {
    width: 140px;
}

.summary-col-actual {
    width: 170px;
}

.summary-col-cmp {
    width: 110px;
}

.summary-col-plan-revenue {
    width: 150px;
}

.summary-col-amount {
    width: 170px;
}

.matrix-table .matrix-input {
    min-width: 72px;
}

.matrix-table .matrix-sticky {
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 2;
}
</style>

<script>
document.querySelectorAll('.js-line-color').forEach(function (el) {
    el.style.backgroundColor = el.dataset.lineColor || '#808080';
});
</script>

@endsection
