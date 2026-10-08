@extends('layouts.app')
@section('title', 'Master Plan')
@section('content')
@include('admin.partials.image-popover')

@php
$canManage = auth()->user()->role === 'admin';
$canEditFabric = $canManage;
$isWarehouse = auth()->user()->role === 'warehouse';
$isQaQc = auth()->user()->role === 'qa_qc';
$isAccountant = auth()->user()->role === 'accountant';
$isPpic = auth()->user()->role === 'ppic';
$hidePpicCols = auth()->user()->role === 'accountant';
$hidePpicExecShipCols = $isPpic;
$hideMidCols = $isAccountant;
@endphp

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif
@if($errors->any())
<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@if(session('success'))
<div class="alert alert-success py-2 px-3 mb-3" style="display: inline-block; max-width: 100%; font-size: .9rem;">
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
        max-height: calc(100vh - 220px);
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
        display: block; /* keep visible to help horizontal scrolling/debug */
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
        width: 100%;
        min-width: max-content;
        --sticky-col-1: 110px;
        --sticky-col-2: 96px;
        --sticky-col-3: 130px;
        --sticky-col-4: 85px;
        --sticky-col-5: 76px;
        --sticky-col-6: 100px;
        --sticky-col-7: 100px;
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

    .masterplan-table td.sticky-6 {
        left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3) + var(--sticky-col-4) + var(--sticky-col-5));
        z-index: 2;
    }

    .masterplan-table td.sticky-7 {
        left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3) + var(--sticky-col-4) + var(--sticky-col-5) + var(--sticky-col-6));
        z-index: 2;
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
    .masterplan-table th.sticky-6 { left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3) + var(--sticky-col-4) + var(--sticky-col-5)); z-index: 22; }
    .masterplan-table th.sticky-7 { left: calc(var(--sticky-col-1) + var(--sticky-col-2) + var(--sticky-col-3) + var(--sticky-col-4) + var(--sticky-col-5) + var(--sticky-col-6)); z-index: 21; }

    /* Keep only the final action columns visible while scrolling horizontally. */
    .masterplan-table th.sticky-action,
    .masterplan-table td.sticky-action {
        position: sticky !important;
        min-width: 56px;
        width: 56px;
        text-align: center;
    }

    .masterplan-table td.sticky-action {
        background: #ffffff;
        z-index: 8;
    }

    .masterplan-table th.sticky-action {
        background: #f8fafc;
        z-index: 28;
    }

    .masterplan-table .sticky-action-delete { right: 0; }
    .masterplan-table .sticky-action-edit { right: 0; }
    .masterplan-table .sticky-action-before-delete {
        right: 56px;
        box-shadow: -2px 0 0 rgba(15, 23, 42, 0.08);
    }

    .masterplan-table th,
    .masterplan-table td {
        padding: 0.35rem 0.5rem;
        white-space: nowrap;
        overflow-wrap: normal;
        word-break: keep-all;
        vertical-align: middle;
        font-size: 0.92rem;
    }

    .masterplan-table th {
        font-weight: 700;
    }
    .masterplan-table .masterplan-inline-input {
        box-sizing: border-box;
        width: 128px;
        min-width: 128px;
        max-width: 128px;
        font-size: .82rem;
    }
    .masterplan-table .masterplan-inline-input[type="date"] {
        width: 138px;
        min-width: 138px;
        max-width: 138px;
    }
    .masterplan-table .masterplan-inline-input[type="number"] {
        width: 88px;
        min-width: 88px;
        max-width: 88px;
    }
    .masterplan-table .masterplan-line-select {
        box-sizing: border-box;
        width: 88px;
        min-width: 88px;
        max-width: 88px;
        padding-left: .35rem;
        padding-right: .25rem;
        font-size: .78rem;
        background-color: #ffffff;
        color: #111827;
    }
    .masterplan-table .masterplan-inline-input.masterplan-inline-notes {
        width: 180px;
        min-width: 180px;
        max-width: 180px;
    }

    .masterplan-scroll .masterplan-table > thead > tr > th {
        white-space: nowrap !important;
        overflow-wrap: normal !important;
        word-break: keep-all;
        word-break: normal !important;
    }

    .masterplan-scroll .masterplan-table > thead > tr > th.col-qty {
        min-width: 88px;
    }

    .masterplan-scroll .masterplan-table > thead > tr > th.col-date-sticky {
        width: 120px;
        min-width: 120px;
    }

    .masterplan-table .col-code {
        width: var(--sticky-col-1);
        min-width: 110px;
    }

    .masterplan-table .col-line {
        width: var(--sticky-col-2);
        min-width: var(--sticky-col-2);
        max-width: var(--sticky-col-2);
    }

    .masterplan-table .col-style {
        width: var(--sticky-col-3);
        min-width: 130px;
    }

    .masterplan-table .col-wide {
        min-width: 130px;
    }

    .masterplan-table .col-date {
        width: 138px;
        min-width: 138px;
        max-width: 138px;
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

    .masterplan-table .col-date-sticky {
        width: var(--sticky-col-6);
        min-width: 100px;
    }

    .masterplan-table .col-number {
        width: 104px;
        min-width: 104px;
        max-width: 104px;
        text-align: right;
    }

    .masterplan-table td.col-date > .masterplan-inline-input,
    .masterplan-table td.col-number > .masterplan-inline-input {
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }

    .masterplan-table .col-gap-right {
        padding-right: 2rem;
    }

    .masterplan-table .col-gap-left {
        padding-left: 2rem;
    }

    .ship-balance-btn {
        min-width: 168px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }

    .masterplan-filter label {
        display: block;
        margin-bottom: 0.35rem;
        font-weight: 500;
    }

    .masterplan-filter .form-control,
    .masterplan-filter-actions .btn {
        height: 42px;
    }

    .masterplan-filter .form-control {
        min-width: 180px;
    }

    .masterplan-filter-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.5rem;
    }

    .masterplan-filter-actions .btn {
        min-width: 88px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }

    .masterplan-filter-actions .btn-success {
        min-width: 110px;
    }

    .line-color-cell {
        border-radius: 4px;
        text-align: center;
        color: #ffffff;
        font-weight: 500;
    }

    #warehouseMasterplanTable th,
    #warehouseMasterplanTable td {
        white-space: nowrap !important;
        overflow-wrap: normal !important;
    }

    #warehouseMasterplanTable .warehouse-notes-cell {
        width: 300px;
        min-width: 220px;
        max-width: 360px;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }

    #warehouseMasterplanTable .warehouse-inline-input {
        min-width: 145px;
    }

    #warehouseMasterplanTable .warehouse-note-input {
        min-width: 220px;
    }

    /* Keep the warehouse table's horizontal scrollbar available at every viewport size. */
    .warehouse-masterplan-scroll {
        scrollbar-width: auto;
        scrollbar-color: rgba(100, 116, 139, .75) #f1f5f9;
    }

    .warehouse-masterplan-scroll::-webkit-scrollbar {
        height: 12px;
    }

    .warehouse-masterplan-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .warehouse-masterplan-scroll::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, .75);
        border-radius: 999px;
    }

    .warehouse-masterplan-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(71, 85, 105, .9);
    }

    /* Leave more room for data columns on laptops by freezing fewer identifiers. */
    @media (max-width: 1400px) {
        .masterplan-table {
            --sticky-col-1: 94px;
            --sticky-col-2: 90px;
            --sticky-col-3: 110px;
            --sticky-col-4: 75px;
            --sticky-col-5: 70px;
            --sticky-col-6: 95px;
            --sticky-col-7: 95px;
        }

        .masterplan-table tbody td.sticky-4,
        .masterplan-table tbody td.sticky-5,
        .masterplan-table tbody td.sticky-6,
        .masterplan-table tbody td.sticky-7 {
            position: static !important;
            left: auto !important;
            z-index: auto !important;
            background: #fff;
            box-shadow: none !important;
        }

        .masterplan-table thead th.sticky-4,
        .masterplan-table thead th.sticky-5,
        .masterplan-table thead th.sticky-6,
        .masterplan-table thead th.sticky-7 {
            left: auto !important;
            z-index: 1 !important;
        }
    }

    @media (max-width: 992px) {
        .masterplan-table tbody td.sticky-3 {
            position: static !important;
            left: auto !important;
            z-index: auto !important;
            background: #fff;
            box-shadow: none !important;
        }

        .masterplan-table thead th.sticky-3 {
            left: auto !important;
            z-index: 1 !important;
        }
    }

    @media (max-width: 576px) {
        .masterplan-table {
            --sticky-col-1: 78px;
            --sticky-col-2: 80px;
        }

        .masterplan-table .masterplan-line-select {
            width: 72px;
            min-width: 72px;
            max-width: 72px;
        }

        .masterplan-table tbody td.sticky-2 {
            position: static !important;
            left: auto !important;
            z-index: auto !important;
            background: #fff;
            box-shadow: none !important;
        }

        .masterplan-table thead th.sticky-2 {
            left: auto !important;
            z-index: 1 !important;
        }

        .masterplan-table th,
        .masterplan-table td {
            padding: 0.25rem 0.35rem;
            font-size: 0.8rem;
        }
    }
</style>

@if($canManage)
<form id="masterplan-save-all-form" method="POST" action="{{ route('admin.masterplan.inline-bulk-update', request()->query()) }}" class="d-none">
    @csrf @method('PUT')
</form>
@endif

<form method="GET" action="{{ url()->current() }}" class="row g-3 mb-4 masterplan-filter" id="filterForm">

    <div class="col-12 col-lg-2">
        <label for="masterplanSearch" class="form-label">Search Master Plan</label>
        <input id="masterplanSearch" type="search" name="search" class="form-control"
            placeholder="Search CU, line, style, PO, dates, status..."
            value="{{ request('search') }}">
    </div>

    @unless($isQaQc || $isAccountant)
    <input type="hidden" name="ship_balance_only" id="shipBalanceFilter"
        value="{{ request('ship_balance_only', 1) }}">
    @endunless

    <div class="col-lg-auto masterplan-filter-actions">

        <!-- SEARCH -->
        <button type="submit" class="btn btn-dark">
            Search
        </button>

        <a href="{{ request()->url() }}"
            class="btn btn-outline-secondary">
            Reset
        </a>

        <a href="{{ route('masterplan.confirm-date', request()->only(['search', 'ship_balance_only'])) }}"
            class="btn btn-outline-primary" title="View rows by Confirm Date">
            <i class="bi bi-calendar-date"></i> Confirm Date
        </a>

        @unless($isQaQc || $isAccountant)
        <button type="button" class="btn ship-balance-btn {{ request('ship_balance_only', 1) ? 'btn-warning' : 'btn-outline-warning' }}"
            id="toggleShipBalanceBtn" title="{{ request('ship_balance_only', 1) ? 'Hiding rows where ExQty is entered and ShipBalance = 0' : 'Showing all rows' }}">
            <i class="bi bi-funnel"></i> 
            {{ request('ship_balance_only', 1) ? 'Hide ' : 'Show all rows' }}
        </button>
        @endunless

        @unless($isQaQc || $isAccountant)
        <a href="{{ route('masterplan.export', request()->query()) }}"
            class="btn btn-success">
            Export Excel
        </a>
        @endunless

        @if($isQaQc)
        <a href="{{ route('masterplan.export', request()->query()) }}" class="btn btn-success">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
        @endif

        @if($canManage)
        <button type="submit" form="masterplan-save-all-form" class="btn btn-success" id="masterplanSaveAllButton" disabled>
            <i class="bi bi-save"></i> Save changes (<span id="masterplanDirtyCount">0</span>)
        </button>
        <button type="button" class="btn btn-outline-primary" id="bulkEditButton" disabled>
            <i class="bi bi-pencil-square"></i> Bulk edit (<span id="bulkEditCount">0</span>)
        </button>
        <a href="{{ route('admin.masterplan.create') }}" class="btn btn-primary">
            Add
        </a>

        <a href="{{ route('admin.holidays.index') }}" class="btn btn-success ">
            View Holiday
        </a>
        @endif

    </div>
</form>

@if($isQaQc || $isAccountant)
@php
    $qaQcGroups = collect($plan)->groupBy(fn ($item) => filled($item->Confirm_date)
        ? substr((string) $item->Confirm_date, 0, 7)
        : 'undated');
@endphp
<div class="table-responsive masterplan-scroll">
    <table class="table masterplan-table">
        <thead>
            <tr>
                <th scope="col" class="col-code sticky-col sticky-1">CU</th>
                <th scope="col" class="col-line sticky-col sticky-2">Line</th>
                <th scope="col" class="col-style sticky-col sticky-3">Style</th>
                <th scope="col" class="col-po sticky-col sticky-4">PO</th>
                <th scope="col" class="col-qty">Order Quantity</th>
                <th scope="col" class="col-date col-date-sticky sticky-col sticky-5">Confirmed Date</th>
                <th scope="col" class="col-date">Warehouse Date</th>
                @if($isQaQc)
                <th scope="col" class="col-wide" style="min-width: 170px">Third-Party Inspection</th>
                <th scope="col" class="col-date">Inspection Date</th>
                <th scope="col" class="col-status">Status</th>
                @else
                <th scope="col" class="col-qty">CMT</th>
                <th scope="col" style="min-width: 220px">Note</th>
                @endif
                @if($isQaQc)
                <th scope="col" class="sticky-action sticky-action-edit">Edit</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($qaQcGroups as $month => $items)
            <tr class="table-warning">
                <th colspan="{{ $isQaQc ? 11 : 9 }}" class="text-start">{{ $month === 'undated' ? 'Chưa có Confirmed Date' : 'Tháng ' . substr($month, 5, 2) . '/' . substr($month, 0, 4) }}</th>
            </tr>
            @foreach($items as $item)
            <tr>
                <td class="col-code sticky-col sticky-1">{{ $item->CU }}</td>
                <td class="col-line sticky-col sticky-2 line-color-cell" data-line-color="{{ $item->LineColor ?? '#808080' }}">{{ $item->Line }}</td>
                <td class="col-style sticky-col sticky-3">{{ $item->Style }}</td>
                <td class="col-po sticky-col sticky-4">{{ $item->PO }}</td>
                <td class="col-qty">{{ $item->Order_Qty }}</td>
                <td class="col-date col-date-sticky sticky-col sticky-5">{{ $item->Confirm_date ?? '' }}</td>
                <td>{{ $item->inWHDate ?? '' }}</td>
                @if($isQaQc)
                <td>{{ $item->{'3rd_PartyInspection'} ?? '' }}</td>
                <td>{{ $item->qa_inspection_date ?? '' }}</td>
                <td><span class="badge bg-{{ ($item->qa_status ?? 'not_approved') === 'approved' ? 'success' : 'secondary' }}">{{ ($item->qa_status ?? 'not_approved') === 'approved' ? 'Approved' : 'Not Approved' }}</span></td>
                <td class="sticky-action sticky-action-edit"><a href="{{ route('masterplan.qa-qc.edit', $item->id) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square"></i> Edit</a></td>
                @else
                <td>{{ $item->CMT ?? '' }}</td>
                <td>
                    <form method="POST" action="{{ route('masterplan.accountant.note.update', array_merge(['id' => $item->id], request()->query())) }}" class="d-flex gap-1">
                        @csrf @method('PUT')
                        <input type="text" name="mps_notes" class="form-control form-control-sm" value="{{ $item->mps_notes ?? '' }}" maxlength="5000" aria-label="Note for {{ $item->CU }}">
                        <button type="submit" class="btn btn-warning btn-sm">Save</button>
                    </form>
                </td>
                @endif
            </tr>
            @endforeach
            @empty
            <tr><td colspan="{{ $isQaQc ? 11 : 9 }}" class="text-center">No data</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div id="masterplanScrollProxy" class="masterplan-scrollbar-proxy" aria-hidden="true">
    <div id="masterplanScrollProxyInner" class="masterplan-scrollbar-proxy-inner"></div>
</div>
@elseif($isWarehouse)
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>MPS Planning</h5></div>
    <div class="table-responsive masterplan-scroll warehouse-masterplan-scroll">
    <table id="warehouseMasterplanTable" class="table table-hover align-middle mb-0 masterplan-table">
        <thead class="table-light"><tr>
            <th>CU</th><th>Line</th><th>Style</th><th>PO</th><th>Order Qty</th><th>Qty Dis</th>
            <th>Confirm Date</th><th>Norm Date</th><th>Fabric Issue Date</th><th>Trims Issue Date</th>
            <th>LT</th><th>FirstOPT</th><th>Finish_SEW</th><th>EX_Fact</th><th>Notes</th><th>Action</th>
        </tr></thead>
        <tbody>
        @php $warehouseLineGroups = collect($plan)->groupBy('Line'); @endphp
        @forelse($warehouseLineGroups as $line => $lineItems)
            <tr class="table-secondary fw-bold">
                <td colspan="16" class="text-start">Line: {{ $line ?: 'Unassigned' }}</td>
            </tr>
            @foreach($lineItems as $item)
            <tr>
                <td>{{ $item->CU }}</td><td class="line-color-cell" data-line-color="{{ preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', (string) $item->LineColor) ? $item->LineColor : '#808080' }}" style="background-color: {{ preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', (string) $item->LineColor) ? $item->LineColor : '#808080' }}">{{ $item->Line }}</td><td>{{ $item->Style }}</td><td>{{ $item->PO }}</td>
                <td>{{ $item->Order_Qty }}</td><td>{{ $item->Qty_dis }}</td><td>{{ $item->Confirm_date }}</td>
                <td><input form="warehouse-plan-{{ $item->id }}" type="date" name="Norm_date" class="form-control form-control-sm warehouse-inline-input" value="{{ $item->Norm_date ? \Carbon\Carbon::parse($item->Norm_date)->format('Y-m-d') : '' }}" aria-label="Norm date for {{ $item->CU }}"></td>
                <td><input form="warehouse-plan-{{ $item->id }}" type="date" name="fabric_issue_date" class="form-control form-control-sm warehouse-inline-input" value="{{ $item->fabric_issue_date ? \Carbon\Carbon::parse($item->fabric_issue_date)->format('Y-m-d') : '' }}" aria-label="Fabric issue date for {{ $item->CU }}"></td>
                <td><input form="warehouse-plan-{{ $item->id }}" type="date" name="trims_issue_date" class="form-control form-control-sm warehouse-inline-input" value="{{ $item->trims_issue_date ? \Carbon\Carbon::parse($item->trims_issue_date)->format('Y-m-d') : '' }}" aria-label="Trims issue date for {{ $item->CU }}"></td><td>{{ $item->lt }}</td>
                <td>{{ $item->calc_FirstOPT ? $item->calc_FirstOPT->format('Y-m-d') : '' }}</td>
                <td>{{ $item->calc_Finish_SEW ? $item->calc_Finish_SEW->format('Y-m-d') : '' }}</td>
                <td>{{ $item->calc_EX_Fact ? $item->calc_EX_Fact->format('Y-m-d') : '' }}</td>
                <td class="warehouse-notes-cell"><input form="warehouse-plan-{{ $item->id }}" type="text" name="mps_notes" class="form-control form-control-sm warehouse-note-input" value="{{ $item->mps_notes ?? '' }}" maxlength="5000" aria-label="Notes for {{ $item->CU }}"></td>
                <td class="text-nowrap"><form id="warehouse-plan-{{ $item->id }}" method="POST" action="{{ route('masterplan.warehouse.update', array_merge(request()->query(), ['id' => $item->id])) }}" class="d-inline">
                    @csrf @method('PUT')
                    <button type="submit" class="btn btn-warning btn-sm">Save</button>
                </form> <a class="btn btn-outline-secondary btn-sm" href="{{ route('masterplan.warehouse.edit', $item->id) }}">Edit</a></td>
            </tr>
            @endforeach
        @empty
            <tr><td colspan="16" class="text-center">No data</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@else
<div class="table-responsive masterplan-scroll">
<table class="table masterplan-table">
    <thead>
        <tr>
            @if($canManage)<th scope="col" style="min-width:48px"><input type="checkbox" id="selectAllMasterplan" aria-label="Select all rows"></th>@endif
            <th scope="col" class="col-code sticky-col sticky-1">CU</th>
            <th scope="col" class="col-line sticky-col sticky-2">Line</th>
            <th scope="col" class="col-style sticky-col sticky-3">Style</th>
            <th scope="col" class="col-po sticky-col sticky-4">PO</th>
            <th scope="col" class="col-qty">Order_Qty</th>
            <th scope="col" class="col-qty col-gap-right sticky-col sticky-5">Qty_dis</th>
            <th scope="col" class="col-date col-date-sticky sticky-col sticky-6">Require_date</th>
            <th scope="col" class="col-date col-date-sticky sticky-col sticky-7">Confirm_date</th>
            @unless($isPpic)
            <th scope="col" class="col-status">MPS Status</th>
            @endunless
            <th scope="col" class="col-priority">Pri.</th>
            @unless($isPpic)
            <th scope="col" class="col-date">Cut Start</th>
            <th scope="col" class="col-date">Cut End</th>
            <th scope="col" class="col-date">Sew Start</th>
            <th scope="col" class="col-date">Sew End</th>
            @endunless
            @unless($hideMidCols)
            <th scope="col" class="col-date">Norm_date</th>
            @endunless
            @if($canManage)
            <th scope="col" class="col-date">Fabric Issue Date</th>
            <th scope="col" class="col-date">Trims Issue Date</th>
            @endif
            @if($isPpic)
            <th scope="col" class="col-date">Fabric Issue Date</th>
            <th scope="col" class="col-date">Trims Issue Date</th>
            <th scope="col" class="col-date">In Warehouse Date</th>
            @endif
            @unless($hidePpicCols)
            @unless($isPpic)
            <th scope="col" class="col-date">inWHDate</th>
            <th scope="col" class="col-wide">3rd_PartyInspection</th>
            <th scope="col" class="col-date">ShipDate2</th>
            <th scope="col" class="col-wide">SoTK</th>
            @endunless
            @unless($hidePpicExecShipCols)
            <th scope="col" class="col-number">ExQty</th>
            <th scope="col" class="col-number">ShipBalance</th>
            @endunless
            @endunless
            <th scope="col" class="col-number">LT</th>
            <th scope="col" class="col-date">FirstOPT</th>
            <th scope="col" class="col-date">Finish_SEW</th>
            <th scope="col" class="col-date">EX_Fact</th>
            @if($canManage)
            <th scope="col" class="col-number">Daily Target</th>
            <th scope="col" class="col-date">Inspection Date</th>
            <th scope="col">QA Status</th>
            <th scope="col">Notes</th>
            @endif
            @if($canEditFabric && !$canManage)
            <th scope="col" class="sticky-action sticky-action-edit {{ $canManage ? 'sticky-action-before-delete' : '' }}">Edit</th>
            @endif
            @if($canManage)
            <th scope="col" class="sticky-action sticky-action-delete">Delete</th>
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
        $actionCols = $canEditFabric ? 1 : 0;
        $tableColspan = $canManage ? 33 : ($isPpic ? 17 : 25 + $actionCols - ($hidePpicCols ? 6 : 0) - ($hideMidCols ? 1 : 0) - ($hidePpicExecShipCols ? 2 : 0));
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
            @php
                $inlineFormId = 'masterplan-row-' . $item->id;
                $inlineLineColor = preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', (string) ($item->LineColor ?? '')) ? $item->LineColor : '#808080';
            @endphp
            @if($canManage)<td><input type="checkbox" class="masterplan-row-select" value="{{ $item->id }}" aria-label="Select {{ $item->CU }}"></td>@endif
            <td class="col-code sticky-col sticky-1">
                @include('admin.partials.image-trigger', ['imageUrl' => !empty($item->ocs_image_path) ? route('masterplan.ocs-image', $item->image_ocs_id, false) : null, 'imageLabel' => $item->CU])
                @if($canManage)
                <form id="{{ $inlineFormId }}" class="d-none" onsubmit="return false">
                    <input type="hidden" id="masterplan-line-color-{{ $item->id }}" name="LineColor" value="{{ $inlineLineColor }}">
                </form>
                @endif
            </td>
            <td class="col-line sticky-col sticky-2 line-color-cell" data-line-color="{{ $item->LineColor ?? '#808080' }}">
                @if($canManage)
                <select form="{{ $inlineFormId }}" name="Line" class="form-select form-select-sm masterplan-inline-input masterplan-line-select" data-color-input="masterplan-line-color-{{ $item->id }}" required aria-label="Line for {{ $item->CU }}">
                    @if(!$lineOptions->contains('name', $item->Line))<option value="{{ $item->Line }}" data-hex="{{ $inlineLineColor }}" selected>{{ $item->Line }}</option>@endif
                    @foreach($lineOptions as $lineOption)<option value="{{ $lineOption->name }}" data-hex="{{ $lineOption->hex_code }}" @selected($item->Line === $lineOption->name)>{{ $lineOption->name }}</option>@endforeach
                </select>
                @else
                {{ $item->Line }}
                @endif
            </td>
            <td class="col-style sticky-col sticky-3">{{ $item->Style }}</td>
            <td class="col-po sticky-col sticky-4">{{ $item->PO }}</td>
            <td class="col-qty">{{ $item->Order_Qty }}</td>
            <td class="col-qty col-gap-right sticky-col sticky-5">
                @if($canManage)<input form="{{ $inlineFormId }}" type="number" name="Qty_dis" class="form-control form-control-sm masterplan-inline-input" min="0" value="{{ $item->Qty_dis }}" aria-label="Distributed quantity for {{ $item->CU }}">
                @else{{ $item->Qty_dis }}@endif
            </td>
            <td class="col-date col-date-sticky sticky-col sticky-6">{{ $item->Require_date ?? '' }}</td>
            <td class="col-date col-date-sticky sticky-col sticky-7">
                @if($canManage)<input form="{{ $inlineFormId }}" type="date" name="Confirm_date" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->Confirm_date ? \Carbon\Carbon::parse($item->Confirm_date)->format('Y-m-d') : '' }}" aria-label="Confirm date for {{ $item->CU }}">
                @else{{ $item->Confirm_date ?? '' }}@endif
            </td>
            @unless($isPpic)
            <td class="col-status">
                @if($canManage)
                <select form="{{ $inlineFormId }}" name="mps_status" class="form-select form-select-sm masterplan-inline-input" aria-label="MPS status for {{ $item->CU }}">
                    <option value="planned" @selected(($item->mps_status ?? 'planned') === 'planned')>Planned</option>
                    <option value="in_production" @selected(($item->mps_status ?? '') === 'in_production')>In Production</option>
                    <option value="completed" @selected(($item->mps_status ?? '') === 'completed')>Completed</option>
                    <option value="on_hold" @selected(($item->mps_status ?? '') === 'on_hold')>On Hold</option>
                </select>
                @else
                @php
                    $statusColor = match($item->mps_status ?? 'planned') {
                        'in_production' => 'success',
                        'completed' => 'primary',
                        'on_hold' => 'danger',
                        default => 'secondary'
                    };
                    $statusLabel = match($item->mps_status ?? 'planned') {
                        'in_production' => '🏭 Prod',
                        'completed' => '✅ Done',
                        'on_hold' => '⏸ Hold',
                        default => '📋 Plan'
                    };
                @endphp
                <span class="badge bg-{{ $statusColor }}" style="font-size:0.7rem;">{{ $statusLabel }}</span>
                @endif
            </td>
            <td class="col-priority">
                @if($canManage)
                <select form="{{ $inlineFormId }}" name="mps_priority" class="form-select form-select-sm masterplan-inline-input" aria-label="Priority for {{ $item->CU }}">
                    <option value="low" @selected(($item->mps_priority ?? '') === 'low')>Low</option>
                    <option value="medium" @selected(($item->mps_priority ?? 'medium') === 'medium')>Medium</option>
                    <option value="high" @selected(($item->mps_priority ?? '') === 'high')>High</option>
                    <option value="urgent" @selected(($item->mps_priority ?? '') === 'urgent')>Urgent</option>
                </select>
                @else
                @php
                    $priColor = match($item->mps_priority ?? 'medium') {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'low' => 'secondary',
                        default => 'info'
                    };
                @endphp
                <span class="badge bg-{{ $priColor }}" style="font-size:0.65rem;">{{ substr($item->mps_priority ?? 'MED', 0, 4) }}</span>
                @endif
            </td>
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="planned_cut_start" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->planned_cut_start ? \Carbon\Carbon::parse($item->planned_cut_start)->format('Y-m-d') : '' }}" aria-label="Cut start for {{ $item->CU }}">@else{{ $item->planned_cut_start ?? '' }}@endif</td>
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="planned_cut_end" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->planned_cut_end ? \Carbon\Carbon::parse($item->planned_cut_end)->format('Y-m-d') : '' }}" aria-label="Cut end for {{ $item->CU }}">@else{{ $item->planned_cut_end ?? '' }}@endif</td>
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="planned_sew_start" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->planned_sew_start ? \Carbon\Carbon::parse($item->planned_sew_start)->format('Y-m-d') : '' }}" aria-label="Sew start for {{ $item->CU }}">@else{{ $item->planned_sew_start ?? '' }}@endif</td>
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="planned_sew_end" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->planned_sew_end ? \Carbon\Carbon::parse($item->planned_sew_end)->format('Y-m-d') : '' }}" aria-label="Sew end for {{ $item->CU }}">@else{{ $item->planned_sew_end ?? '' }}@endif</td>
            @endunless
            @unless($hideMidCols)
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="Norm_date" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->Norm_date ? \Carbon\Carbon::parse($item->Norm_date)->format('Y-m-d') : '' }}" aria-label="Norm date for {{ $item->CU }}">@else{{ $item->Norm_date }}@endif</td>
            @endunless
            @if($canManage)
            <td><input form="{{ $inlineFormId }}" type="date" name="fabric_issue_date" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->fabric_issue_date ? \Carbon\Carbon::parse($item->fabric_issue_date)->format('Y-m-d') : '' }}" aria-label="Fabric issue date for {{ $item->CU }}"></td>
            <td><input form="{{ $inlineFormId }}" type="date" name="trims_issue_date" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->trims_issue_date ? \Carbon\Carbon::parse($item->trims_issue_date)->format('Y-m-d') : '' }}" aria-label="Trims issue date for {{ $item->CU }}"></td>
            @endif
            @if($isPpic)
            <td>{{ $item->fabric_issue_date ?? '' }}</td>
            <td>{{ $item->trims_issue_date ?? '' }}</td>
            <td>{{ $item->inWHDate ?? '' }}</td>
            @endif
            @unless($hidePpicCols)
            @unless($isPpic)
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="inWHDate" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->inWHDate ? \Carbon\Carbon::parse($item->inWHDate)->format('Y-m-d') : '' }}" aria-label="In warehouse date for {{ $item->CU }}">@else{{ $item->inWHDate }}@endif</td>
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="text" name="3rd_PartyInspection" class="form-control form-control-sm masterplan-inline-input" maxlength="50" value="{{ $item->{'3rd_PartyInspection'} ?? '' }}" aria-label="Third party inspection for {{ $item->CU }}">@else{{ $item->{'3rd_PartyInspection'} ?? '' }}@endif</td>
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="ShipDate2" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->ShipDate2 ? \Carbon\Carbon::parse($item->ShipDate2)->format('Y-m-d') : '' }}" aria-label="Ship date for {{ $item->CU }}">@else{{ $item->ShipDate2 }}@endif</td>
            <td>@if($canManage)<input form="{{ $inlineFormId }}" type="text" name="SoTK" class="form-control form-control-sm masterplan-inline-input" maxlength="50" value="{{ $item->SoTK ?? '' }}" aria-label="SoTK for {{ $item->CU }}">@else{{ $item->SoTK }}@endif</td>
            @endunless
            @unless($hidePpicExecShipCols)
            <td class="col-number">@if($canManage)<input form="{{ $inlineFormId }}" type="number" name="ExQty" class="form-control form-control-sm masterplan-inline-input" min="0" value="{{ $item->ExQty }}" aria-label="ExQty for {{ $item->CU }}">@else{{ $item->ExQty }}@endif</td>
            <td class="col-number">{{ $item->ShipBalance }}</td>
            @endunless
            @endunless
            <td class="col-number">@if($canManage)<input form="{{ $inlineFormId }}" type="number" name="lt" class="form-control form-control-sm masterplan-inline-input" min="0" value="{{ $item->lt }}" aria-label="Lead time for {{ $item->CU }}">@else{{ $item->lt }}@endif</td>
            <td class="col-date">@if($canManage)<input form="{{ $inlineFormId }}" type="date" name="FirstOPT" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->FirstOPT ? \Carbon\Carbon::parse($item->FirstOPT)->format('Y-m-d') : '' }}" aria-label="First OPT for {{ $item->CU }}">@else{{ $item->calc_FirstOPT ? $item->calc_FirstOPT->format('Y-m-d') : '' }}@endif</td>
            <td>{{ $item->calc_Finish_SEW ? $item->calc_Finish_SEW->format('Y-m-d') : '' }}</td>
            <td>{{$item->calc_EX_Fact ? $item->calc_EX_Fact->format('Y-m-d') : ''  }}</td>
            @if($canManage)
            <td><input form="{{ $inlineFormId }}" type="number" name="daily_target_qty" class="form-control form-control-sm masterplan-inline-input" min="0" value="{{ $item->daily_target_qty }}" aria-label="Daily target for {{ $item->CU }}"></td>
            <td><input form="{{ $inlineFormId }}" type="date" name="qa_inspection_date" class="form-control form-control-sm masterplan-inline-input" value="{{ $item->qa_inspection_date ? \Carbon\Carbon::parse($item->qa_inspection_date)->format('Y-m-d') : '' }}" aria-label="Inspection date for {{ $item->CU }}"></td>
            <td><select form="{{ $inlineFormId }}" name="qa_status" class="form-select form-select-sm masterplan-inline-input" aria-label="QA status for {{ $item->CU }}">
                <option value="not_approved" @selected(($item->qa_status ?? 'not_approved') === 'not_approved')>Not Approved</option>
                <option value="approved" @selected(($item->qa_status ?? '') === 'approved')>Approved</option>
            </select></td>
            <td><input form="{{ $inlineFormId }}" type="text" name="mps_notes" class="form-control form-control-sm masterplan-inline-input masterplan-inline-notes" maxlength="5000" value="{{ $item->mps_notes ?? '' }}" aria-label="Notes for {{ $item->CU }}"></td>
            @elseif($canEditFabric)
            <td class="sticky-action sticky-action-edit {{ $canManage ? 'sticky-action-before-delete' : '' }}">
                <a href="{{ route('masterplan.fabric.edit', $item->id) }}" class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil-square"></i>
                </a>
            </td>
            @endif
            @if($canManage)
            <td class="sticky-action sticky-action-delete">
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
            <td colspan="{{ $canManage ? 33 : ($isPpic ? 17 : 25 + (($canEditFabric ? 1 : 0) + ($canManage ? 2 : 0)) - ($hidePpicCols ? 6 : 0) - ($hideMidCols ? 1 : 0)) }}" class="text-center">No data</td>
        </tr>
        @endif
    </tbody>
</table>
</div>
<div id="masterplanScrollProxy" class="masterplan-scrollbar-proxy" aria-hidden="true">
    <div id="masterplanScrollProxyInner" class="masterplan-scrollbar-proxy-inner"></div>
</div>
@endif

<script>
    function applyMasterplanLineSelectColor(select) {
        const color = select.selectedOptions[0]?.dataset.hex || '';
        if (!/^#(?:[A-Fa-f0-9]{3}){1,2}$/.test(color)) return;

        const colorInput = document.getElementById(select.dataset.colorInput);
        if (colorInput) colorInput.value = color;

        const cell = select.closest('td');
        if (cell) cell.style.backgroundColor = color;

        // Fill the dropdown with the selected line color and keep its label readable.
        const normalized = color.length === 4
            ? '#' + color.slice(1).split('').map((digit) => digit + digit).join('')
            : color;
        const red = parseInt(normalized.slice(1, 3), 16);
        const green = parseInt(normalized.slice(3, 5), 16);
        const blue = parseInt(normalized.slice(5, 7), 16);
        const luminance = 0.299 * red + 0.587 * green + 0.114 * blue;
        select.style.backgroundColor = color;
        select.style.color = luminance > 150 ? '#111827' : '#ffffff';
        select.style.borderColor = color;
        select.style.setProperty('--masterplan-line-color', color);
    }

    document.querySelectorAll('.masterplan-line-select').forEach((select) => {
        applyMasterplanLineSelectColor(select);
        select.addEventListener('change', () => applyMasterplanLineSelectColor(select));
    });

    document.querySelectorAll('.masterplan-line-select').forEach((select) => {
        select.addEventListener('focus', function () {
            this.style.boxShadow = '0 0 0 .2rem color-mix(in srgb, var(--masterplan-line-color, #0d6efd) 25%, transparent)';
        });
        select.addEventListener('blur', function () {
            this.style.boxShadow = '';
        });
    });

    (function () {
        const batchForm = document.getElementById('masterplan-save-all-form');
        const saveButton = document.getElementById('masterplanSaveAllButton');
        const dirtyCount = document.getElementById('masterplanDirtyCount');
        if (!batchForm || !saveButton || !dirtyCount) return;

        const rowForms = Array.from(document.querySelectorAll('form[id^="masterplan-row-"]'));
        const initialValues = new Map(rowForms.map((form) => [
            form.id.slice('masterplan-row-'.length),
            JSON.stringify(Array.from(new FormData(form).entries())),
        ]));

        const changedForms = () => rowForms.filter((form) => {
            const id = form.id.slice('masterplan-row-'.length);
            return JSON.stringify(Array.from(new FormData(form).entries())) !== initialValues.get(id);
        });
        const refreshButton = () => {
            const changed = changedForms().length;
            dirtyCount.textContent = changed;
            saveButton.disabled = changed === 0;
        };

        document.querySelectorAll('.masterplan-inline-input').forEach((input) => {
            input.addEventListener('input', refreshButton);
            input.addEventListener('change', refreshButton);
        });

        batchForm.addEventListener('submit', (event) => {
            const changed = changedForms();
            if (!changed.length) {
                event.preventDefault();
                return;
            }

            batchForm.querySelectorAll('[data-batch-entry]').forEach((input) => input.remove());
            changed.forEach((form) => {
                const id = form.id.slice('masterplan-row-'.length);
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = `rows[${id}][id]`;
                idInput.value = id;
                idInput.dataset.batchEntry = 'true';
                batchForm.appendChild(idInput);

                for (const [name, value] of new FormData(form).entries()) {
                    if (['_token', '_method', 'CU'].includes(name)) continue;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `rows[${id}][${name}]`;
                    input.value = value;
                    input.dataset.batchEntry = 'true';
                    batchForm.appendChild(input);
                }
            });
        });

        refreshButton();
    })();

    (function () {
        const selectAll = document.getElementById('selectAllMasterplan');
        const rows = Array.from(document.querySelectorAll('.masterplan-row-select'));
        const button = document.getElementById('bulkEditButton');
        const count = document.getElementById('bulkEditCount');
        if (!selectAll || !button || !count) return;
        const refresh = () => {
            const selected = rows.filter((checkbox) => checkbox.checked).length;
            count.textContent = selected;
            button.disabled = selected === 0;
            selectAll.checked = rows.length > 0 && selected === rows.length;
            selectAll.indeterminate = selected > 0 && selected < rows.length;
        };
        selectAll.addEventListener('change', () => {
            rows.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            refresh();
        });
        rows.forEach((checkbox) => checkbox.addEventListener('change', refresh));
        button.addEventListener('click', () => {
            const selected = rows.filter((checkbox) => checkbox.checked);
            if (!selected.length) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @json(route('admin.masterplan.bulk-edit'));
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = @json(csrf_token());
            form.appendChild(token);
            selected.forEach((checkbox) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        });
    })();

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
    document.getElementById('toggleShipBalanceBtn')?.addEventListener('click', function(e) {
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
        if ('ResizeObserver' in window) {
            const proxyResizeObserver = new ResizeObserver(syncProxyGeometry);
            proxyResizeObserver.observe(masterplanScroll);
            const masterplanTable = masterplanScroll.querySelector('.masterplan-table');
            if (masterplanTable) proxyResizeObserver.observe(masterplanTable);
        }
        if (document.fonts?.ready) {
            document.fonts.ready.then(syncProxyGeometry);
        }
        syncProxyGeometry();
    }

    // Apply dynamic line colors from data attributes to avoid template parsing issues in inline CSS.
    document.querySelectorAll('.line-color-cell').forEach(function(cell) {
        cell.style.backgroundColor = cell.dataset.lineColor || '#808080';
    });

</script>
@endsection
