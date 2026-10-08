@extends('layouts.app')
@section('title', 'Master Plan by Confirm Date')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1 fw-bold">Master Plan by Confirm Date</h4>
        <div class="text-muted">Choose a day, month, or year to view the matching codes.</div>
    </div>
    <a href="{{ route('masterplan.view', request()->only(['search', 'ship_balance_only'])) }}" class="btn btn-outline-secondary">Back to Master Plan</a>
</div>

<form method="GET" action="{{ route('masterplan.confirm-date') }}" class="row g-3 align-items-end mb-4">
    @foreach(['search', 'ship_balance_only'] as $key)
        @if(request()->filled($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">@endif
    @endforeach
    <div class="col-12 col-sm-4 col-lg-3">
        <label for="confirmPeriod" class="form-label">Filter by</label>
        <select id="confirmPeriod" name="period" class="form-select">
            <option value="day" @selected($period === 'day')>Day</option>
            <option value="month" @selected($period === 'month')>Month</option>
            <option value="year" @selected($period === 'year')>Year</option>
        </select>
    </div>
    <div class="col-12 col-sm-4 col-lg-3">
        <label for="confirmPeriodValue" class="form-label" id="confirmPeriodValueLabel">{{ ucfirst($period) }}</label>
        <input id="confirmPeriodValue" name="value" class="form-control"
            type="{{ $period === 'day' ? 'date' : ($period === 'year' ? 'number' : 'month') }}"
            value="{{ $value }}"
            data-day="{{ $periodValues['day'] }}"
            data-month="{{ $periodValues['month'] }}"
            data-year="{{ $periodValues['year'] }}"
            min="1900" max="2200" required>
    </div>
    <div class="col-12 col-sm-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">View codes</button>
        <a href="{{ route('masterplan.confirm-date', array_merge(request()->only(['search', 'ship_balance_only']), ['period' => 'month', 'value' => now()->format('Y-m')])) }}" class="btn btn-outline-secondary">This month</a>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">{{ $plan->count() }} code(s)</h5>
    <span class="text-muted">{{ ucfirst($period) }}: {{ $value }}</span>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>CU</th><th>Line</th><th>Style</th><th>PO</th>
                <th class="text-end">Order Quantity</th><th class="text-end">Qty Dis</th>
                <th>Require Date</th><th>Confirm Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plan as $item)
                @php
                    $lineColor = preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', (string) ($item->LineColor ?? ''))
                        ? $item->LineColor
                        : '#808080';
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $item->CU }}</td>
                    <td class="text-center" style="background-color: {{ $lineColor }}; color: #111; font-weight: 600">{{ $item->Line }}</td>
                    <td>{{ $item->Style }}</td>
                    <td>{{ $item->PO }}</td>
                    <td class="text-end">{{ $item->Order_Qty }}</td>
                    <td class="text-end">{{ $item->Qty_dis }}</td>
                    <td>{{ $item->Require_date ?? '' }}</td>
                    <td>{{ $item->Confirm_date ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No Master Plan codes match this Confirm Date period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
(() => {
    const period = document.getElementById('confirmPeriod');
    const value = document.getElementById('confirmPeriodValue');
    const label = document.getElementById('confirmPeriodValueLabel');
    if (!period || !value || !label) return;

    const syncPeriodInput = () => {
        const selected = period.value;
        value.type = selected === 'day' ? 'date' : (selected === 'year' ? 'number' : 'month');
        value.value = value.dataset[selected];
        value.min = selected === 'year' ? '1900' : '';
        value.max = selected === 'year' ? '2200' : '';
        label.textContent = selected.charAt(0).toUpperCase() + selected.slice(1);
    };

    period.addEventListener('change', syncPeriodInput);
})();
</script>
@endsection
