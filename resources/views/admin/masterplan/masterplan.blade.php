@extends('layouts.app')
@section('title', 'Master Plan')
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

<style>
    .masterplan-table {
        table-layout: auto;
    }

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
        min-width: 110px;
    }

    .masterplan-table .col-line {
        min-width: 120px;
    }

    .masterplan-table .col-wide {
        min-width: 130px;
    }

    .masterplan-table .col-date {
        min-width: 115px;
    }

    .masterplan-table .col-po {
        min-width: 85px;
    }

    .masterplan-table .col-qty {
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
</style>

<form method="GET" action="{{ url()->current() }}" class="row g-3 mb-4" id="filterForm">

    <div class="col-auto">
        <label>ETA1</label>
        <input type="date" name="to_date" class="form-control form-control-sm" style="width: 160px;"
            value="{{ request('to_date') }}">
    </div>

    <div class="col-auto">
        <label>PO</label>
        <input type="text" name="po" class="form-control form-control-sm" style="width: 160px;"
            placeholder="Fill PO"
            value="{{ request('po') }}">
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

        <button type="button" class="btn {{ request('ship_balance_only') ? 'btn-warning' : 'btn-outline-warning' }}" 
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

        <a href="{{ route('admin.holidays.index') }}" class="btn btn-success ">
            View Holiday
        </a>
        @endif

    </div>
</form>

<div class="table-responsive">
<table class="table masterplan-table">
    <thead>
        <tr>
            <th scope="col" class="col-code">CU</th>
            <th scope="col" class="col-line">Line</th>
            <th scope="col" class="col-wide">Style</th>
            <th scope="col" class="col-po">PO</th>
            <th scope="col" class="col-qty col-gap-right">Qty_dis</th>
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
            @if($canManage)
            <th scope="col">Edit</th>
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
        $colorLines = ['green', 'blue', 'orange', 'yellow'];
        $totalColorQty = collect($plan)->filter(function ($item) use ($colorLines) {
            return in_array(strtolower(trim((string) ($item->Line ?? ''))), $colorLines, true);
        })->sum('Qty_dis');
        $totalSubconQty = collect($plan)->filter(function ($item) use ($colorLines) {
            return !in_array(strtolower(trim((string) ($item->Line ?? ''))), $colorLines, true);
        })->sum('Qty_dis');
        $tableColspan = $canManage ? 27 : 25;
        @endphp

        @foreach($grouped as $line => $items)
        @php
        $isColorLine = in_array(strtolower(trim((string) $line)), $colorLines, true);
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

        @foreach($items as $item)
        <tr>
            <td>{{ $item->CU }}</td>
            <td style="background-color: {{ $item->LineColor ?? '#808080' }}; border-radius: 4px; text-align: center; color: white; font-weight: 500;">
                {{ $item->Line }}
            </td>
            <td>{{ $item->Style }}</td>
            <td class="col-po">{{ $item->PO }}</td>
            <td class="col-qty col-gap-right">{{ $item->Qty_dis }}</td>
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
            @if($canManage)
            <td>
                <a href="{{ route('admin.masterplan.edit', $item->id) }}"
                    class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </td>
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
            <td colspan="{{ $canManage ? 27 : 25 }}" class="text-center">No data</td>
        </tr>
        @endif
    </tbody>
</table>
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
</script>
@endsection