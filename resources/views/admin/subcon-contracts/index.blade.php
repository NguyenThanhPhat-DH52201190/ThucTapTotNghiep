@extends('layouts.app')

@section('title', 'Subcon Contract')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h4 class="mb-1">Subcon Contract</h4><div class="text-muted small">CU đang nằm trên line có loại Subcon trong Line Master.</div></div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<form method="GET" class="row g-2 mb-3">
    <div class="col-sm-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Tìm CU, style, PO, customer"></div>
    <div class="col-sm-3"><select class="form-select" name="line"><option value="">All Subcon lines</option>@foreach($subconLines as $line)<option value="{{ $line }}" @selected(request('line') === $line)>{{ $line }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('admin.subcon-contracts.index') }}">Reset</a></div>
</form>

@php
$fields = [
    'code' => ['Code', 'text'], 'unit_price' => ['Unit Price', 'number'],
    'contract_no' => ['Số Hợp Đồng', 'text'], 'contract_date' => ['Ngày Hợp Đồng', 'date'],
    'appendix_no' => ['Số Phụ lục HĐ', 'text'], 'appendix_date' => ['Ngày phụ lục', 'date'],
    'tax_code' => ['MST', 'text'], 'customs_doc_no' => ['Số KLKX', 'text'], 'customs_doc_date' => ['Ngày KLKX', 'date'],
    'form_12' => ['Mẫu 12', 'text'], 'form_12_approved_date' => ['Ngày duyệt mẫu 12', 'date'],
    'form_18a' => ['Mẫu 18A', 'text'], 'form_18a_received_date' => ['Ngày nhận mẫu 18A', 'date'],
    'out_date' => ['OUT DATE', 'date'], 'truck_plate' => ['TRUCK PLATE', 'text'], 'in_date' => ['IN DATE', 'date'],
    'truck_plate_2' => ['TRUCK PLATE2', 'text'], 'invoice_no' => ['Invoice', 'text'], 'invoice_date' => ['INV Date', 'date'],
    'actual_received_qty' => ['SL THUC NHAN', 'number'], 'payment_date' => ['NGAY THANH TOAN', 'date'],
    'export_bank_account' => ['STK XUAT', 'text'], 'customs_declaration_date' => ['NGAY TK', 'date'], 'notes' => ['Ghi chú', 'text'],
];
$columnWidths = [85, 155, 120, 125, 130, 180, 75, 135, 175, 100, 125, 175, 155, 165, 150, 130, 150, 150, 125, 155, 125, 155, 130, 145, 130, 145, 140, 130, 145, 155, 150, 150, 245, 155, 95];
@endphp

<div class="table-responsive border rounded bg-white">
<table class="table table-sm table-bordered table-hover align-middle mb-0 subcon-contract-table">
    <colgroup>@foreach($columnWidths as $width)<col style="width: {{ $width }}px">@endforeach</colgroup>
    <thead class="table-light"><tr>
        <th>CU</th><th>Subcon / Line</th><th>Style</th><th>PO#</th><th>Color</th><th>Customer</th><th>Qty</th><th>Norm Date</th><th>Material Out Date</th>
        @foreach($fields as [$label, $type])<th>{{ $label }}</th>@endforeach
        <th>Total Amount</th><th>Action</th>
    </tr></thead>
    <tbody>
    @forelse($contracts as $row)
        <tr>
            <td class="fw-semibold text-nowrap">{{ $row->CU }}<form id="contract-{{ $row->mtp_id }}" method="POST" action="{{ route('admin.subcon-contracts.update', $row->mtp_id) }}">@csrf @method('PUT')</form></td><td class="text-nowrap">{{ $row->Line }}</td><td>{{ $row->style }}</td><td>{{ $row->po }}</td><td>{{ $row->garment_color }}</td><td>{{ $row->customer }}</td>
            <td class="text-end">{{ $row->Qty_dis }}</td>
            <td class="text-nowrap">{{ $row->Norm_date ? \Carbon\Carbon::parse($row->Norm_date)->format('d/m/Y') : '' }}</td>
            <td class="text-nowrap">{{ $row->fabric_issue_date ? \Carbon\Carbon::parse($row->fabric_issue_date)->format('d/m/Y') : '' }}</td>
                @foreach($fields as $name => [$label, $type])
                    <td><input form="contract-{{ $row->mtp_id }}" class="form-control form-control-sm" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $row->$name ?? '') }}" @if($type === 'number') step="0.01" min="0" @endif aria-label="{{ $label }} for {{ $row->CU }}"></td>
                @endforeach
                <td class="text-end text-nowrap">{{ number_format((float) ($row->Qty_dis ?? 0) * (float) ($row->unit_price ?? 0), 2) }}</td>
                <td><button form="contract-{{ $row->mtp_id }}" class="btn btn-sm btn-primary">Save</button></td>
        </tr>
    @empty
        <tr><td colspan="{{ 11 + count($fields) }}" class="text-center text-muted py-4">No Subcon CU found.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<style>
    .subcon-contract-table { table-layout: fixed; width: max-content; min-width: 100%; }
    .subcon-contract-table th { position: sticky; top: 0; z-index: 1; white-space: normal !important; word-break: keep-all; overflow-wrap: normal; line-height: 1.2; vertical-align: middle; }
    .subcon-contract-table td { overflow: hidden; text-overflow: ellipsis; }
    .subcon-contract-table td input { width: 100%; min-width: 0; padding-left: .35rem; padding-right: .35rem; }
    .subcon-contract-table td input[type=date] { min-width: 0; }
    .subcon-contract-table td input[name=notes] { min-width: 0; }
</style>
@endsection
