@extends('layouts.app')
@section('title', 'Master Plan')
@section('content')

@php
$canManage = auth()->user()->role === 'admin';
$canEditFabric = in_array(auth()->user()->role, ['admin', 'ppic'], true);
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

<style>
    html,
    body {
        overflow-x: hidden;
    }

    .flex-grow-1 {
        min-width: 0;
    }

    .masterplan-scroll {
        position: relative;
        overflow-x: auto !important;
        overflow-y: auto !important;
        width: 100%;
        max-width: 100%;
        max-height: calc(100vh - 300px);
        isolation: isolate;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .masterplan-scroll::-webkit-scrollbar {
        height: 0;
    }

    .masterplan-scrollbar-proxy {
        position: fixed;
        bottom: 6px;
        height: 20px;
        overflow-x: auto;
        overflow-y: hidden;
        background: transparent;
        z-index: 1200;
        display: none;
        scrollbar-color: rgba(100, 116, 139, 0.7) transparent;
    }

    .masterplan-scrollbar-proxy-inner {
        height: 2px;
    }

    .masterplan-scrollbar-proxy::-webkit-scrollbar {
        height: 16px;
    }

    .masterplan-scrollbar-proxy::-webkit-scrollbar-track {
        background: transparent;
    }

    .masterplan-scrollbar-proxy::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, 0.65);
        border-radius: 999px;
        min-height: 30px;
    }

    .masterplan-scrollbar-proxy::-webkit-scrollbar-thumb:hover {
        background: rgba(71, 85, 105, 0.85);
    }

    .masterplan-table {
        table-layout: auto;
        border-collapse: separate;
        border-spacing: 0;
        width: max-content;
        min-width: max-content;
        --sticky-col-1: 110px;
        --sticky-col-2: 120px;
        --sticky-col-3: 130px;
        --sticky-col-4: 85px;
        --sticky-col-5: 76px;
    }

    .masterplan-table th.sticky-col,
    .masterplan-table td.sticky-col {
        position: sticky !important;
        background: #f8fafc;
        left: 0;
    }

    .masterplan-table td.sticky-col {
        background: #ffffff;
        z-index: 2;
    }

    .masterplan-table th.sticky-col {
        z-index: 3;
    }

    .masterplan-table td.sticky-1 {
        left: 0;
        z-index: 7;
    }

    .masterplan-table td.sticky-2 {
        left: var(--sticky-col-1);
        z-index: 6;
    }

    .masterplan-table td.sticky-3 {
        left: calc(var(--sticky-col-1) + var(--sticky-col-2));
        z-index: 5;
    }

    .masterplan-table td.sticky-4 {
        left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3));
        z-index: 4;
    }

    .masterplan-table td.sticky-5 {
        left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3) + var(--sticky-col-4));
        z-index: 3;
        box-shadow: 2px 0 0 rgba(15, 23, 42, 0.08);
    }

    .masterplan-table thead th {
        position: sticky;
        top: 0;
        background: #f8fafc;
    }

    .masterplan-table th.sticky-1 { left: 0; z-index: 27; }
    .masterplan-table th.sticky-2 { left: var(--sticky-col-1); z-index: 26; }
    .masterplan-table th.sticky-3 { left: calc(var(--sticky-col-1) + var(--sticky-col-2)); z-index: 25; }
    .masterplan-table th.sticky-4 { left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3)); z-index: 24; }
    .masterplan-table th.sticky-5 { left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3) + var(--sticky-col-4)); z-index: 23; }

    .masterplan-table th,
    .masterplan-table td {
        padding: 0.75rem 1rem;
        white-space: nowrap;
        vertical-align: middle;
    }

    .masterplan-table th {
        font-weight: 700;
    }

    .masterplan-table .col-code {
        width: var(--sticky-col-1);
        min-width: 110px;
    }

    .masterplan-table .col-line {
        width: var(--sticky-col-2);
        min-width: 120px;
    }

    .masterplan-table .col-style {
        width: var(--sticky-col-3);
        min-width: 130px;
    }

    .masterplan-table .col-wide {
        min-width: 130px;
    }

    .masterplan-table .col-date {
        min-width: 115px;
    }

    .masterplan-table .col-po {
        width: var(--sticky-col-4);
        min-width: 85px;
    }

    .masterplan-table .col-qty {
        width: var(--sticky-col-5);
        min-width: 60px;
        text-align: center;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    .masterplan-table .col-number {
        min-width: 90px;
        text-align: right;
    }

    .masterplan-table .col-gap-right {
        padding-right: 2rem;
    }

    .masterplan-table .col-gap-left {
        padding-left: 2rem;
    }

    .ship-balance-btn {
        min-width: 150px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }

    .line-color-cell {
        border-radius: 4px;
        text-align: center;
        color: #ffffff;
        font-weight: 500;
    }
</style>

<form method="GET" action="{{ url()->current() }}" class="row g-3 mb-4" id="filterForm">

    <div class="col-auto">
        <label>CU</label>
        <input type="text" name="cu" class="form-control form-control-sm" style="width: 160px;"
            placeholder="Fill CU"
            value="{{ request('cu') }}">
    </div>

    <div class="col-auto">
        <label>Style</label>
        <input type="text" name="style" class="form-control form-control-sm" style="width: 160px;"
            placeholder="Fill Style"
            value="{{ request('style') }}">
    </div>

    <input type="hidden" name="ship_balance_only" id="shipBalanceFilter" 
        value="{{ request('ship_balance_only', 0) }}">

    <div class="col-md-4 d-flex align-items-end gap-2">

        <!-- SEARCH -->
        <button type="submit" class="btn btn-dark">
            Search
        </button>

        <a href="{{ request()->url() }}"
            class="btn btn-outline-secondary">
            Reset
        </a>

        <button type="button" class="btn ship-balance-btn {{ request('ship_balance_only') ? 'btn-warning' : 'btn-outline-warning' }}" 
            id="toggleShipBalanceBtn" title="Filter by ShipBalance">
            <i class="bi bi-funnel"></i> 
            {{ request('ship_balance_only') ? 'ALL CU' : 'With ShipBalance' }}
        </button>

        <a href="{{ route('masterplan.export', request()->query()) }}"
            class="btn btn-success">
            Export Excel
        </a>

        @if($canManage)
        <a href="{{ route('admin.masterplan.create') }}" class="btn btn-primary">
            Add
        </a>

        <a href="{{ route('admin.colors.index') }}" class="btn btn-outline-primary">
            Line Colors
        </a>

        <a href="{{ route('admin.holidays.index') }}" class="btn btn-success ">
            View Holiday
        </a>
        @endif

    </div>
</form>

<div class="table-responsive masterplan-scroll">
<table class="table masterplan-table">
    <thead>
        <tr>
            <th scope="col" class="col-code sticky-col sticky-1">CU</th>
            <th scope="col" class="col-line sticky-col sticky-2">Line</th>
            <th scope="col" class="col-style sticky-col sticky-3">Style</th>
            <th scope="col" class="col-po sticky-col sticky-4">PO</th>
            <th scope="col" class="col-qty">Order_Qty</th>
            <th scope="col" class="col-qty col-gap-right sticky-col sticky-5">Qty_dis</th>
            <th scope="col" class="col-wide col-gap-left">Fabric1</th>
            <th scope="col" class="col-date">ETA1</th>
            <th scope="col" class="col-date">Actual</th>
            <th scope="col" class="col-wide">Fabric2</th>
            <th scope="col" class="col-date">ETA2</th>
            <th scope="col" class="col-wide">Linning</th>
            <th scope="col" class="col-date">ETA3</th>
            <th scope="col" class="col-wide">Pocket</th>
            <th scope="col" class="col-date">ETA4</th>
            <th scope="col" class="col-wide">Trim</th>
            <th scope="col" class="col-date">Norm_date</th>
            <th scope="col" class="col-date">inWHDate</th>
            <th scope="col" class="col-wide">3rd_PartyInspection</th>
            <th scope="col" class="col-date">ShipDate2</th>
            <th scope="col" class="col-wide">SoTK</th>
            <th scope="col" class="col-number">ExQty</th>
            <th scope="col" class="col-number">ShipBalance</th>
            <th scope="col" class="col-number">LT</th>
            <th scope="col" class="col-date">FirstOPT</th>
            <th scope="col" class="col-date">Finish_SEW</th>
            <th scope="col" class="col-date">EX_Fact</th>
            @if($canEditFabric)
            <th scope="col">Edit</th>
            @endif
            @if($canManage)
            <th scope="col">Delete</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @if(isset($plan) && count($plan))
        @php
        $grouped = collect($plan)->groupBy('Line');
        $subconHeaderShown = false;
        $colorTotalShown = false;
        $totalColorQty = collect($plan)->filter(function ($item) {
            return strtoupper((string) ($item->LineCate ?? 'SUBCON')) === 'GSV';
        })->sum('Qty_dis');
        $totalSubconQty = collect($plan)->filter(function ($item) {
            return strtoupper((string) ($item->LineCate ?? 'SUBCON')) !== 'GSV';
        })->sum('Qty_dis');
        $actionCols = ($canEditFabric ? 1 : 0) + ($canManage ? 1 : 0);
        $tableColspan = 27 + $actionCols;
        @endphp

        @foreach($grouped as $line => $items)
        @php
        $isColorLine = strtoupper((string) ($items->first()->LineCate ?? 'SUBCON')) === 'GSV';
        $lineItems = $items->values();
        $monthlyQtyByFinish = $lineItems
            ->filter(fn($row) => !empty($row->calc_Finish_SEW))
            ->groupBy(fn($row) => $row->calc_Finish_SEW->format('Y-m'))
            ->map(fn($rows) => $rows->sum('Qty_dis'));
        @endphp

        @if(!$isColorLine && !$subconHeaderShown)
        <tr class="table-info fw-bold">
            <td colspan="4" class="text-end">GSV season total:</td>
            <td>{{ $totalColorQty }}</td>
            <td colspan="{{ $tableColspan - 5 }}"></td>
        </tr>
        @php $colorTotalShown = true; @endphp

        <tr class="table-dark">
            <td colspan="{{ $tableColspan }}" class="fw-bold text-center">Masterplan for Subcon</td>
        </tr>
        @php $subconHeaderShown = true; @endphp
        @endif

        @foreach($lineItems as $index => $item)
        <tr>
            <td class="col-code sticky-col sticky-1">{{ $item->CU }}</td>
            <td class="col-line sticky-col sticky-2 line-color-cell" data-line-color="{{ $item->LineColor ?? '#808080' }}">
                {{ $item->Line }}
            </td>
            <td class="col-style sticky-col sticky-3">{{ $item->Style }}</td>
            <td class="col-po sticky-col sticky-4">{{ $item->PO }}</td>
            <td class="col-qty">{{ $item->Order_Qty }}</td>
            <td class="col-qty col-gap-right sticky-col sticky-5">{{ $item->Qty_dis }}</td>
            <td class="col-gap-left">{{ $item->Fabric1 }}</td>
            <td>{{ $item->ETA1 }}</td>
            <td>{{ $item->Actual }}</td>
            <td>{{ $item->Fabric2 }}</td>
            <td>{{ $item->ETA2 }}</td>
            <td>{{ $item->Linning }}</td>
            <td>{{ $item->ETA3 }}</td>
            <td>{{ $item->Pocket }}</td>
            <td>{{ $item->ETA4 }}</td>
            <td>{{ $item->Trim }}</td>
            <td>{{ $item->Norm_date }}</td>
            <td>{{ $item->inWHDate }}</td>
            <td>{{ $item->{'3rd_PartyInspection'} ?? '' }}</td>
            <td>{{ $item->ShipDate2 }}</td>
            <td>{{ $item->SoTK }}</td>
            <td class="col-number">{{ $item->ExQty }}</td>
            <td class="col-number">{{ $item->ShipBalance }}</td>
            <td class="col-number">{{ $item->lt }}</td>
            <td>{{ $item->calc_FirstOPT ? $item->calc_FirstOPT->format('Y-m-d') : '' }}</td>
            <td>{{ $item->calc_Finish_SEW ? $item->calc_Finish_SEW->format('Y-m-d') : '' }}</td>
            <td>{{$item->calc_EX_Fact ? $item->calc_EX_Fact->format('Y-m-d') : ''  }}</td>
            @if($canEditFabric)
            <td>
                <a href="{{ $canManage ? route('admin.masterplan.edit', $item->id) : route('masterplan.fabric.edit', $item->id) }}"
                    class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </td>
            @endif
            @if($canManage)
            <td>
                <form method="POST" action="{{ route('admin.masterplan.destroy', $item->id) }}"
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

        @php
        $currentFinishMonth = $item->calc_Finish_SEW ? $item->calc_Finish_SEW->format('Y-m') : null;
        $nextItem = $lineItems->get($index + 1);
        $nextFinishMonth = ($nextItem && $nextItem->calc_Finish_SEW) ? $nextItem->calc_Finish_SEW->format('Y-m') : null;
        $isMonthEnd = $currentFinishMonth && $currentFinishMonth !== $nextFinishMonth;
        @endphp

        @if($isMonthEnd)
        <tr class="table-light fw-semibold">
            <td colspan="4" class="text-end">Subtotal {{ $isColorLine ? 'Line' : 'Subcon' }} {{ $line }} (Finish_SEW {{ \Carbon\Carbon::createFromFormat('Y-m', $currentFinishMonth)->format('m/Y') }}):</td>
            <td>{{ $monthlyQtyByFinish->get($currentFinishMonth, 0) }}</td>
            <td colspan="{{ $tableColspan - 5 }}"></td>
        </tr>
        @endif
        @endforeach

        {{-- TOTAL ROW --}}
        <tr class="fw-bold 
            @if($line == 'Green') table-success
            @elseif($line == 'Blue') table-primary
            @elseif($line == 'Orange') table-warning
            @elseif($line == 'Yellow') table-warning
            @else table-secondary
            @endif
        ">
            <td colspan="4" class="text-end">Total Line {{ $line }}:</td>
            <td>{{ $items->sum('Qty_dis') }}</td>
            <td colspan="{{ $tableColspan - 5 }}"></td>
        </tr>

        @endforeach

        @if(!$colorTotalShown)
        <tr class="table-info fw-bold">
            <td colspan="4" class="text-end">GSV season total:</td>
            <td>{{ $totalColorQty }}</td>
            <td colspan="{{ $tableColspan - 5 }}"></td>
        </tr>
        @endif

        @if($subconHeaderShown)
        <tr class="table-warning-subtle fw-bold">
            <td colspan="4" class="text-end">Subcon season total:</td>
            <td>{{ $totalSubconQty }}</td>
            <td colspan="{{ $tableColspan - 5 }}"></td>
        </tr>
        @endif
        @else
        <tr>
            <td colspan="{{ 27 + (($canEditFabric ? 1 : 0) + ($canManage ? 1 : 0)) }}" class="text-center">No data</td>
        </tr>
        @endif
    </tbody>
</table>
</div>
<div id="masterplanScrollProxy" class="masterplan-scrollbar-proxy" aria-hidden="true">
    <div id="masterplanScrollProxyInner" class="masterplan-scrollbar-proxy-inner"></div>
</div>

<script>
    function calculate() {
        let firstOPT = document.querySelector('[name="FirstOPT"]').value;
        let lt = document.querySelector('[name="lt"]').value;

        if (!firstOPT || !lt) {
            document.getElementById('finishSew').value = '';
            document.getElementById('exFact').value = '';
            return;
        }

        fetch(`/calc-date?firstOPT=${firstOPT}&lt=${lt}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('finishSew').value = data.finish || '';
                document.getElementById('exFact').value = data.ex || '';
            });
    }

    // Only bind events when inputs exist on the page.
    const firstOptInput = document.querySelector('[name="FirstOPT"]');
    const ltInput = document.querySelector('[name="lt"]');

    if (firstOptInput && ltInput) {
        firstOptInput.addEventListener('change', calculate);
        ltInput.addEventListener('input', calculate);
        window.onload = calculate;
    }

    // Toggle ShipBalance filter button
    document.getElementById('toggleShipBalanceBtn').addEventListener('click', function(e) {
        e.preventDefault();
        const filterInput = document.getElementById('shipBalanceFilter');
        const filterForm = document.getElementById('filterForm');
        
        filterInput.value = filterInput.value == 1 ? 0 : 1;
        filterForm.submit();
    });

    // Always-visible horizontal scrollbar pinned to viewport bottom,
    // synced with the table's own horizontal scroll.
    const masterplanScroll = document.querySelector('.masterplan-scroll');
    const scrollProxy = document.getElementById('masterplanScrollProxy');
    const scrollProxyInner = document.getElementById('masterplanScrollProxyInner');

    let syncingFromTable = false;
    let syncingFromProxy = false;

    function syncProxyGeometry() {
        if (!masterplanScroll || !scrollProxy || !scrollProxyInner) return;

        const hasOverflow = masterplanScroll.scrollWidth > masterplanScroll.clientWidth + 1;

        if (!hasOverflow) {
            scrollProxy.style.display = 'none';
            return;
        }

        const rect = masterplanScroll.getBoundingClientRect();
        const left = Math.max(rect.left, 0);
        const width = Math.max(0, Math.min(rect.width, window.innerWidth - left));

        scrollProxy.style.display = 'block';
        scrollProxy.style.left = left + 'px';
        scrollProxy.style.width = width + 'px';
        scrollProxyInner.style.width = masterplanScroll.scrollWidth + 'px';

        if (!syncingFromTable) {
            scrollProxy.scrollLeft = masterplanScroll.scrollLeft;
        }
    }

    if (masterplanScroll && scrollProxy) {
        masterplanScroll.addEventListener('scroll', function() {
            syncingFromTable = true;
            if (!syncingFromProxy) {
                scrollProxy.scrollLeft = masterplanScroll.scrollLeft;
            }
            syncingFromTable = false;
        });

        scrollProxy.addEventListener('scroll', function() {
            syncingFromProxy = true;
            if (!syncingFromTable) {
                masterplanScroll.scrollLeft = scrollProxy.scrollLeft;
            }
            syncingFromProxy = false;
        });

        window.addEventListener('resize', syncProxyGeometry);
        window.addEventListener('load', syncProxyGeometry);
        syncProxyGeometry();
    }

    // Apply dynamic line colors from data attributes to avoid template parsing issues in inline CSS.
    document.querySelectorAll('.line-color-cell').forEach(function(cell) {
        cell.style.backgroundColor = cell.dataset.lineColor || '#808080';
    });

</script>
@endsection