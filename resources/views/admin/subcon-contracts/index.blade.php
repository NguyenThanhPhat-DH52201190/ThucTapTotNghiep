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
$columns = [
    ['label' => 'Subcon / Line', 'source' => 'Line'], ['label' => 'CS#', 'source' => 'CU'],
    ['label' => 'Style', 'source' => 'style'], ['label' => 'PO#', 'source' => 'po'], ['label' => 'Customer', 'source' => 'customer'],
    ['label' => 'Qty', 'source' => 'Qty_dis'], ['label' => 'Unit Price', 'field' => 'unit_price', 'input' => 'number'],
    ['label' => 'Total Amount', 'total' => true],
    ['label' => 'Số Hợp Đồng', 'field' => 'contract_no', 'input' => 'text'], ['label' => 'Ngày Hợp Đồng', 'field' => 'contract_date', 'input' => 'date'],
    ['label' => 'Số Phụ lục HĐ', 'field' => 'appendix_no', 'input' => 'text'], ['label' => 'Ngày phụ lục', 'field' => 'appendix_date', 'input' => 'date'],
    ['label' => 'MST', 'field' => 'tax_code', 'input' => 'text'], ['label' => 'Số KLKX', 'field' => 'customs_doc_no', 'input' => 'text'],
    ['label' => 'Ngày KLKX', 'field' => 'customs_doc_date', 'input' => 'date'], ['label' => 'Mẫu 12', 'field' => 'form_12', 'input' => 'text'],
    ['label' => 'Ngày duyệt mẫu 12', 'field' => 'form_12_approved_date', 'input' => 'date'], ['label' => 'Mẫu 18A', 'field' => 'form_18a', 'input' => 'text'],
    ['label' => 'Ngày nhận mẫu 18A', 'field' => 'form_18a_received_date', 'input' => 'date'], ['label' => 'OUT DATE', 'field' => 'out_date', 'input' => 'date'],
    ['label' => 'TRUCK PLATE', 'field' => 'truck_plate', 'input' => 'text'], ['label' => 'IN DATE', 'field' => 'in_date', 'input' => 'date'],
    ['label' => 'TRUCK PLATE2', 'field' => 'truck_plate_2', 'input' => 'text'], ['label' => 'Invoice', 'field' => 'invoice_no', 'input' => 'text'],
    ['label' => 'INV Date', 'field' => 'invoice_date', 'input' => 'date'], ['label' => 'SL THUC NHAN', 'field' => 'actual_received_qty', 'input' => 'number'],
    ['label' => 'NGAY THANH TOAN', 'field' => 'payment_date', 'input' => 'date'], ['label' => 'STK XUAT', 'source' => 'masterplan_sotk'],
    ['label' => 'NGAY TK', 'field' => 'customs_declaration_date', 'input' => 'date'], ['label' => 'Ghi chú', 'field' => 'notes', 'input' => 'text'],
];
$columnWidths = [155, 100, 120, 125, 180, 75, 125, 155, 175, 155, 165, 150, 130, 150, 150, 125, 155, 125, 155, 130, 145, 130, 145, 140, 130, 145, 155, 150, 150, 245];
$role = auth()->user()->role;
$editableFields = match ($role) {
    'admin' => null,
    'ppic' => ['unit_price', 'contract_no', 'contract_date', 'appendix_no', 'appendix_date', 'tax_code', 'out_date', 'truck_plate', 'in_date', 'truck_plate_2', 'invoice_no', 'invoice_date', 'actual_received_qty', 'notes'],
    'ie' => ['customs_doc_no', 'customs_doc_date', 'form_12', 'form_12_approved_date', 'form_18a', 'form_18a_received_date', 'notes'],
    'accountant' => ['payment_date', 'notes'],
    default => ['notes'],
};
$groupedContracts = $contracts->groupBy('Line');
@endphp

<form method="POST" action="{{ route('admin.subcon-contracts.update-all', request()->only('line', 'search')) }}">
@csrf @method('PUT')
<div class="d-flex justify-content-end mb-2"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save All</button></div>
<div class="table-responsive border rounded bg-white subcon-contract-scroll">
<table class="table table-sm table-bordered table-hover align-middle mb-0 subcon-contract-table">
    <colgroup>@foreach($columnWidths as $width)<col style="width: {{ $width }}px">@endforeach</colgroup>
    <thead class="table-light"><tr>@foreach($columns as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($groupedContracts as $line => $lineContracts)
      @foreach($lineContracts as $row)
        <tr>
            @foreach($columns as $column)
                    <td @if(isset($column['total'])) class="text-end text-nowrap" @elseif(isset($column['source']) && $column['source'] === 'Line') class="text-nowrap line-color-cell" data-line-color="{{ $row->line_color ?? '#808080' }}" @elseif(isset($column['date']) || isset($column['source'])) class="text-nowrap" @endif>
                    @if(isset($column['total']))
                        {{ number_format((float) ($row->Qty_dis ?? 0) * (float) ($row->unit_price ?? 0), 2) }}
                    @elseif(isset($column['field']))
                        @if($editableFields === null || in_array($column['field'], $editableFields, true))
                            <input class="form-control form-control-sm" type="{{ $column['input'] }}" name="contracts[{{ $row->masterplan_id }}][{{ $column['field'] }}]" value="{{ old('contracts.'.$row->masterplan_id.'.'.$column['field'], $row->{$column['field']} ?? '') }}" @if($column['input'] === 'number') step="0.01" min="0" @endif aria-label="{{ $column['label'] }} for {{ $row->CU }}">
                        @else
                            {{ $row->{$column['field']} ?? '' }}
                        @endif
                    @elseif(isset($column['date']))
                        {{ $row->{$column['source']} ? \Carbon\Carbon::parse($row->{$column['source']})->format('d/m/Y') : '' }}
                    @else
                        {{ $row->{$column['source']} ?? '' }}
                    @endif
                </td>
            @endforeach
        </tr>
      @endforeach
        <tr class="table-secondary fw-bold">
            <td colspan="5" class="text-end">Total Line {{ $line }}:</td>
            <td class="text-end">{{ $lineContracts->sum('Qty_dis') }}</td>
            <td></td>
            <td class="text-end">{{ number_format($lineContracts->sum(fn ($item) => (float) ($item->Qty_dis ?? 0) * (float) ($item->unit_price ?? 0)), 2) }}</td>
            <td colspan="{{ count($columns) - 8 }}"></td>
        </tr>
    @empty
        <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">No Subcon CU found.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="d-flex justify-content-end mt-2"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save All</button></div>
</form>
<div class="subcon-contract-scrollbar" aria-label="Cuộn ngang bảng Subcon Contract">
    <div class="subcon-contract-scrollbar-inner"></div>
</div>
<style>
    .subcon-contract-table { table-layout: fixed; width: max-content; min-width: 100%; }
    .subcon-contract-table th { position: sticky; top: 0; z-index: 1; white-space: normal !important; word-break: keep-all; overflow-wrap: normal; line-height: 1.2; vertical-align: middle; }
    .subcon-contract-table td { overflow: hidden; text-overflow: ellipsis; }
    .subcon-contract-table td input { width: 100%; min-width: 0; padding-left: .35rem; padding-right: .35rem; }
    .subcon-contract-table td input[type=date] { min-width: 0; }
    .subcon-contract-table .line-color-cell { text-align: center; color: #fff; font-weight: 500; border-radius: 4px; }
    .subcon-contract-scrollbar { position: fixed; z-index: 1040; left: 240px; right: 0; bottom: 0; height: 18px; overflow-x: auto; overflow-y: hidden; background: #f1f3f5; border-top: 1px solid #adb5bd; }
    .subcon-contract-scrollbar-inner { height: 1px; }
    @media (max-width: 991.98px) { .subcon-contract-scrollbar { left: 0; } }
</style>
<script>
    (() => {
        const tableScroll = document.querySelector('.subcon-contract-scroll');
        const fixedScroll = document.querySelector('.subcon-contract-scrollbar');
        const fixedInner = document.querySelector('.subcon-contract-scrollbar-inner');
        if (!tableScroll || !fixedScroll || !fixedInner) return;

        let syncing = false;
        const syncWidth = () => {
            fixedInner.style.width = `${tableScroll.scrollWidth}px`;
            fixedScroll.scrollLeft = tableScroll.scrollLeft;
        };
        tableScroll.addEventListener('scroll', () => {
            if (syncing) return;
            syncing = true;
            fixedScroll.scrollLeft = tableScroll.scrollLeft;
            syncing = false;
        });
        fixedScroll.addEventListener('scroll', () => {
            if (syncing) return;
            syncing = true;
            tableScroll.scrollLeft = fixedScroll.scrollLeft;
            syncing = false;
        });
        new ResizeObserver(syncWidth).observe(tableScroll);
        new ResizeObserver(syncWidth).observe(tableScroll.querySelector('table'));
        window.addEventListener('resize', syncWidth);
        document.querySelectorAll('.line-color-cell').forEach((cell) => {
            cell.style.backgroundColor = cell.dataset.lineColor || '#808080';
        });
        syncWidth();
    })();
</script>
@endsection
