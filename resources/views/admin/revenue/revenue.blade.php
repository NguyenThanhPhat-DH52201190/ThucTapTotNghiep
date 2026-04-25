@extends('layouts.app')
@section('title', 'Revenue ')
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
<form method="GET" action="{{ url()->current() }}" class="row g-3 mb-4">

    <div class="col-md-2">
    <label>CS</label>
    <input type="text" name="cs" class="form-control"
        placeholder="Fill CS"
        value="{{ request('cs') }}">
</div>

    <div class="col-md-2">
    <label>Month</label>
    <input type="month" name="month" class="form-control"
        value="{{ request('month', now()->format('Y-m')) }}">
</div>

    <div class="col-md-8 d-flex align-items-end gap-2 flex-wrap revenue-actions">

        <!-- SEARCH -->
        <button type="submit" class="btn btn-dark revenue-action-btn">
            Search
        </button>

        <a href="{{ request()->url() }}"
            class="btn btn-outline-secondary revenue-action-btn">
            Reset
        </a>

        <a href="{{ route('revenue.export', request()->query()) }}"
            class="btn btn-success revenue-action-btn">
            Export Excel
        </a>

        <a href="{{ route('revenue.monthly-report', ['year' => substr(request('month', now()->format('Y-m')), 0, 4)]) }}"
            class="btn btn-outline-dark revenue-action-btn text-nowrap">
            Monthly Report
        </a>

        @if($canManage)
        <a href="{{ route('admin.revenue.create') }}" class="btn btn-primary revenue-action-btn">
            Add
        </a>
        @endif

    </div>
</form>
<table class="table">
    <thead>
        <tr>
            <th scope="col">SewingLine</th>
            <th scope="col">CS</th>
            <th scope="col">Sewingmp</th>
            <th scope="col">Workhrs</th>
            <th scope="col">Distribution</th>
            <th scope="col">Planout</th>
            <th scope="col">Actualout</th>
            <th scope="col">Cmp</th>
            @if($canManage)
            <th scope="col">Edit</th>
            <th scope="col">Delete</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @php
        $grouped = collect($revenues)->groupBy('LineCate');
        $canManageColspan = $canManage ? 10 : 8;
        @endphp

        @foreach($grouped as $cate => $items)
        <tr>
            <td colspan="{{ $canManageColspan }}" class="revenue-group-cell revenue-group-{{ strtolower($cate) }}">
                {{ strtoupper($cate) }}
            </td>
        </tr>

        @foreach($items->groupBy('SewingLine') as $line => $lineItems)
        <tr class="table-light fw-bold">
            <td colspan="{{ $canManageColspan }}">
                Line: {{ $line }}
            </td>
        </tr>

        @foreach($lineItems as $item)
        <tr>
            <td class="line-badge js-line-color" data-line-color="{{ $item->LineColor ?? '#808080' }}">
                {{ $item->SewingLine }}
            </td>
            <td>{{ $item->CS }}</td>
            <td>{{ $item->sewingmp }}</td>
            <td>{{ $item->workhrs }}</td>
            <td>{{ $item->Distribution }}</td>
            <td>{{ $item->planout }}</td>
            <td>{{ $item->actualout }}</td>
            <td>{{ $item->cmp }}</td>
            @if($canManage)
            <td>
                <a href="{{ route('admin.revenue.edit', $item->id) }}"
                    class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </td>
            <td>
                <form method="POST" action="{{ route('admin.revenue.destroy', $item->id) }}"
                    onsubmit="return confirm('Are you sure?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </td>
            @endif
        </tr>
        @endforeach

        <tr>
            <td colspan="{{ $canManageColspan }}" class="text-end">
                <a href="{{ route('revenue.daily.line', ['line' => $line, 'month' => request('month', now()->format('Y-m'))]) }}" class="btn btn-outline-primary btn-sm">
                    Daily Revenue - {{ $line }}
                </a>
            </td>
        </tr>
        @endforeach
        @endforeach
    </tbody>
</table>

<style>
.table {
    border-collapse: separate;
    border-spacing: 0;
    --revenue-header-height: 46px;
}

.table thead th {
    position: sticky;
    top: 0;
    z-index: 12;
    background: #f8fafc;
}

.line-badge {
    border-radius: 4px;
    text-align: center;
    color: #fff;
    font-weight: 500;
}

.table tbody td.revenue-group-cell {
    position: sticky;
    top: var(--revenue-header-height);
    z-index: 8;
    color: #ffffff !important;
    font-weight: 800;
    font-size: 1.05rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding-top: 0.8rem;
    padding-bottom: 0.8rem;
    border-left: 8px solid transparent;
    box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.15), 0 3px 10px rgba(15, 23, 42, 0.2);
}

.table tbody td.revenue-group-gsv {
    background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
    border-left-color: #facc15;
    color: #ffffff !important;
}

.table tbody td.revenue-group-subcon {
    background: linear-gradient(90deg, #111827 0%, #374151 100%);
    border-left-color: #22c55e;
    color: #ffffff !important;
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
</style>

<script>
document.querySelectorAll('.js-line-color').forEach(function (el) {
    el.style.backgroundColor = el.dataset.lineColor || '#808080';
});
</script>

@endsection
