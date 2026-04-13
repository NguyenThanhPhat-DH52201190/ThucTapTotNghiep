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
<form method="GET" action="{{ url()->current() }}" class="row g-3 mb-4">

    <div class="col-md-2">
        <label>Request Day</label>
        <input type="date" name="to_date" class="form-control"
            value="{{ request('to_date') }}">
    </div>

    <div class="col-md-2">
        <label>PO</label>
        <input type="text" name="po" class="form-control"
            placeholder="Fill PO"
            value="{{ request('po') }}">
    </div>

    <div class="col-md-2">
        <label>Style</label>
        <input type="text" name="style" class="form-control"
            placeholder="Fill Style"
            value="{{ request('style') }}">
    </div>

    <div class="col-md-4 d-flex align-items-end gap-2">

        <!-- SEARCH -->
        <button type="submit" class="btn btn-dark">
            Search
        </button>

        <a href="{{ request()->url() }}"
            class="btn btn-outline-secondary">
            Reset
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

<table class="table">
    <thead>
        <tr>
            <th scope="col">CU</th>
            <th scope="col">Line</th>
            <th scope="col">Rdate</th>
            <th scope="col">ETADay</th>
            <th scope="col">ActDate</th>
            <th scope="col">PO</th>
            <th scope="col">LT</th>
            <th scope="col">FirstOPT</th>
            <th scope="col">Finish_SEW</th>
            <th scope="col">EX_Fact</th>
            <th scope="col">Qty_dis</th>
            <th scope="col">Style</th>
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
        $tableColspan = $canManage ? 14 : 12;
        @endphp

        @foreach($grouped as $line => $items)
        @php
        $isColorLine = in_array(strtolower(trim((string) $line)), $colorLines, true);
        @endphp

        @if(!$isColorLine && !$subconHeaderShown)
        <tr class="table-info fw-bold">
            <td colspan="10" class="text-end">GSV season total:</td>
            <td>{{ $totalColorQty }}</td>
            @if($canManage)
            <td colspan="3"></td>
            @else
            <td colspan="1"></td>
            @endif
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
            <td>
                <span class="badge 
                @if($item->Line == 'Green') bg-success
                @elseif($item->Line == 'Blue') bg-primary
                @elseif($item->Line == 'Orange') bg-warning-subtle
                @elseif($item->Line == 'Yellow') bg-warning
                @else bg-secondary
                @endif
            ">
                    {{ $item->Line }}
                </span>
            </td>
            <td>{{ $item->Rdate }}</td>
            <td>{{ $item->ETADate }}</td>
            <td>{{ $item->ActDate }}</td>
            <td>{{ $item->PO }}</td>
            <td>{{ $item->lt }}</td>
            <td>{{ $item->calc_FirstOPT ? $item->calc_FirstOPT->format('Y-m-d') : '' }}</td>
            <td>{{ $item->calc_Finish_SEW ? $item->calc_Finish_SEW->format('Y-m-d') : '' }}</td>
            <td>{{$item->calc_EX_Fact ? $item->calc_EX_Fact->format('Y-m-d') : ''  }}</td>
            <td>{{ $item->Qty_dis }}</td>
            <td>{{ $item->Style }}</td>
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

        {{-- DÒNG TỔNG --}}
        <tr class="fw-bold 
            @if($line == 'Green') table-success
            @elseif($line == 'Blue') table-primary
            @elseif($line == 'Orange') table-warning
            @elseif($line == 'Yellow') table-warning
            @else table-secondary
            @endif
        ">
            <td colspan="10" class="text-end">Total Line {{ $line }}:</td>
            <td>{{ $items->sum('Qty_dis') }}</td>
            @if($canManage)
            <td colspan="3"></td>
            @else
            <td colspan="1"></td>
            @endif
        </tr>

        @endforeach

        @if(!$colorTotalShown)
        <tr class="table-info fw-bold">
            <td colspan="10" class="text-end">GSV season total:</td>
            <td>{{ $totalColorQty }}</td>
            @if($canManage)
            <td colspan="3"></td>
            @else
            <td colspan="1"></td>
            @endif
        </tr>
        @endif

        @if($subconHeaderShown)
        <tr class="table-warning-subtle fw-bold">
            <td colspan="10" class="text-end">Subcon season total:</td>
            <td>{{ $totalSubconQty }}</td>
            @if($canManage)
            <td colspan="3"></td>
            @else
            <td colspan="1"></td>
            @endif
        </tr>
        @endif
        @else
        <tr>
            <td colspan="{{ $canManage ? 14 : 12 }}" class="text-center">No data</td>
        </tr>
        @endif
    </tbody>
</table>

<script>
    function calculate() {
        let firstOPT = document.querySelector('[name="FirstOPT"]').value;
        let lt = document.querySelector('[name="lt"]').value;

        if (!firstOPT || !lt) return;

        fetch(`/calc-date?firstOPT=${firstOPT}&lt=${lt}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('finishSew').value = data.finish || '';
                document.getElementById('exFact').value = data.ex || '';
            });
    }

    // Chỉ bind sự kiện khi các input có tồn tại trên trang.
    const firstOptInput = document.querySelector('[name="FirstOPT"]');
    const ltInput = document.querySelector('[name="lt"]');

    if (firstOptInput && ltInput) {
        firstOptInput.addEventListener('change', calculate);
        ltInput.addEventListener('input', calculate);
        window.onload = calculate;
    }
</script>
@endsection