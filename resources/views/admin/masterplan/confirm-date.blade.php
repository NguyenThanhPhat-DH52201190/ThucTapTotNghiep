@extends('layouts.app')
@section('title', 'Master Plan by Confirm Date')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1 fw-bold">Master Plan by Confirm Date</h4>
        <div class="text-muted">Choose a month and year to view matching cutsheets.</div>
    </div>
    <a href="{{ route('masterplan.view', request()->only(['search', 'ship_balance_only'])) }}" class="btn btn-outline-secondary">Back to Master Plan</a>
</div>

<form method="GET" action="{{ route('masterplan.confirm-date') }}" class="row g-3 align-items-end mb-4">
    @foreach(['search', 'ship_balance_only'] as $key)
        @if(request()->filled($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">@endif
    @endforeach
    <div class="col-12 col-sm-3 col-lg-2">
        <label for="confirmMonth" class="form-label">Confirm Date month</label>
        <input id="confirmMonth" name="month" type="month" class="form-control" value="{{ $month }}" required>
    </div>
    <div class="col-12 col-sm-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="{{ route('masterplan.confirm-date', array_merge(request()->only(['search', 'ship_balance_only']), ['month' => now()->format('Y-m')])) }}" class="btn btn-outline-secondary">This month</a>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">{{ $plan->count() }} {{ $plan->count() === 1 ? 'cutsheet' : 'cutsheets' }}</h5>
    <span class="text-muted">Month: {{ $month }}</span>
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
                <tr><td colspan="8" class="text-center text-muted py-4">No Master Plan codes match this Confirm Date month.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
