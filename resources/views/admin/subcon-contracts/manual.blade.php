@extends('layouts.app')

@section('title', 'Manual Subcon Contract')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h4 class="mb-1">Manual Subcon Contract</h4><div class="text-muted small">Dữ liệu độc lập, nhập trực tiếp và không liên kết Master Plan.</div></div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="{{ route('admin.subcon-contracts.index') }}">Master Plan Subcon Contract</a>
        <a class="btn btn-success" href="{{ route('admin.subcon-contracts-manual.export', request()->only('line', 'search')) }}"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
        @if(in_array(auth()->user()->role, ['admin', 'ppic'], true))
            <form method="POST" action="{{ route('admin.subcon-contracts-manual.store') }}" class="d-flex gap-2 align-items-center">
                @csrf
                <input class="form-control" type="number" name="count" value="1" min="1" max="100" required aria-label="Số dòng cần thêm" style="width: 90px">
                <button class="btn btn-primary">Add rows</button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="GET" class="row g-2 mb-3">
    <div class="col-sm-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Tìm CU, style, PO, customer"></div>
    <div class="col-sm-3"><select class="form-select" name="line"><option value="">All Subcon lines</option>@foreach($subconLines as $line)<option value="{{ $line }}" @selected(request('line') === $line)>{{ $line }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('admin.subcon-contracts-manual.index') }}">Reset</a></div>
</form>

@php
$columns = [
    ['label' => 'Subcon / Line', 'field' => 'subcon_line', 'input' => 'text'], ['label' => 'CS#', 'field' => 'cs_no', 'input' => 'text'],
    ['label' => 'Style', 'field' => 'style', 'input' => 'text'], ['label' => 'PO#', 'field' => 'po', 'input' => 'text'], ['label' => 'Customer', 'field' => 'customer', 'input' => 'text'],
    ['label' => 'Qty', 'field' => 'qty', 'input' => 'number'], ['label' => 'Unit Price', 'field' => 'unit_price', 'input' => 'number'],
    ['label' => 'Total Amount', 'total' => true],
    ['label' => 'Số Hợp Đồng', 'field' => 'contract_no', 'input' => 'text'], ['label' => 'Ngày Hợp Đồng', 'field' => 'contract_date', 'input' => 'date'],
    ['label' => 'Số Phụ lục HĐ', 'field' => 'appendix_no', 'input' => 'text'], ['label' => 'Ngày phụ lục', 'field' => 'appendix_date', 'input' => 'date'],
    ['label' => 'MST', 'field' => 'tax_code', 'input' => 'text'], ['label' => 'Công đoạn gia công', 'field' => 'processing_stage', 'input' => 'text'], ['label' => 'Số KLKX', 'field' => 'customs_doc_no', 'input' => 'text'],
    ['label' => 'Ngày KLKX', 'field' => 'customs_doc_date', 'input' => 'date'], ['label' => 'Mẫu 12', 'field' => 'form_12', 'input' => 'text'],
    ['label' => 'Ngày duyệt mẫu 12', 'field' => 'form_12_approved_date', 'input' => 'date'], ['label' => 'Mẫu 18A', 'field' => 'form_18a', 'input' => 'text'],
    ['label' => 'Ngày nhận mẫu 18A', 'field' => 'form_18a_received_date', 'input' => 'date'], ['label' => 'OUT DATE', 'field' => 'out_date', 'input' => 'date'],
    ['label' => 'TRUCK PLATE', 'field' => 'truck_plate', 'input' => 'text'], ['label' => 'IN DATE', 'field' => 'in_date', 'input' => 'date'],
    ['label' => 'Invoice', 'field' => 'invoice_no', 'input' => 'text'],
    ['label' => 'INV Date', 'field' => 'invoice_date', 'input' => 'date'], ['label' => 'SL THUC NHAN', 'field' => 'actual_received_qty', 'input' => 'number'],
    ['label' => 'NGAY THANH TOAN', 'field' => 'payment_date', 'input' => 'date'], ['label' => 'STK XUAT', 'field' => 'export_bank_account', 'input' => 'text'],
    ['label' => 'NGAY TK', 'field' => 'customs_declaration_date', 'input' => 'date'], ['label' => 'Ghi chú', 'field' => 'notes', 'input' => 'text'],
];
$canHighlight = in_array(auth()->user()->role, ['admin', 'imex'], true);
if ($canHighlight) array_splice($columns, 2, 0, [['label' => 'Highlight', 'highlight' => true]]);
$columnWidths = [155, 100, 120, 125, 180, 75, 125, 155, 175, 155, 165, 150, 130, 155, 150, 150, 125, 155, 125, 155, 130, 145, 130, 145, 140, 145, 155, 150, 150, 245];
if ($canHighlight) array_splice($columnWidths, 2, 0, [125]);
$role = auth()->user()->role;
$canEdit = in_array($role, ['admin', 'ppic', 'accountant', 'imex'], true);
$editableFields = match ($role) {
    'admin' => null,
    'ppic' => ['subcon_line', 'cs_no', 'style', 'po', 'customer', 'qty', 'unit_price', 'contract_no', 'contract_date', 'appendix_no', 'appendix_date', 'tax_code', 'processing_stage', 'out_date', 'truck_plate', 'in_date', 'invoice_no', 'invoice_date', 'actual_received_qty', 'notes'],
    'accountant' => ['payment_date', 'notes'],
    'imex' => ['customs_doc_no', 'customs_doc_date', 'form_12', 'form_12_approved_date', 'form_18a', 'form_18a_received_date', 'customs_declaration_date', 'notes'],
    default => [],
};
@endphp

@if($canEdit)
<form method="POST" action="{{ route('admin.subcon-contracts-manual.update-all', request()->only('line', 'search')) }}">
@csrf @method('PUT')
<div class="d-flex justify-content-end mb-2"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save All</button></div>
@endif
<div class="table-responsive border rounded bg-white subcon-contract-scroll">
<table class="table table-sm table-bordered table-hover align-middle mb-0 subcon-contract-table">
    <colgroup>@foreach($columnWidths as $width)<col style="width: {{ $width }}px">@endforeach</colgroup>
    <thead class="table-light"><tr>@foreach($columns as $column)<th class="{{ $loop->index < 6 ? 'subcon-sticky-col subcon-sticky-' . ($loop->index + 1) : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($contracts as $row)
        <tr class="{{ ($row->highlight_color ?? '') === 'yellow' ? 'subcon-highlight-yellow' : (($row->highlight_color ?? '') === 'green' ? 'subcon-highlight-green' : '') }}">
            @foreach($columns as $column)
                    @php
                        $cellClasses = [];
                        if ($loop->index < 6) $cellClasses[] = 'subcon-sticky-col subcon-sticky-' . ($loop->index + 1);
                        if (isset($column['total'])) $cellClasses[] = 'text-end text-nowrap';
                        if (isset($column['source']) && $column['source'] === 'Line') $cellClasses[] = 'text-nowrap line-color-cell';
                        elseif (isset($column['date']) || isset($column['source'])) $cellClasses[] = 'text-nowrap';
                    @endphp
                    <td class="{{ implode(' ', $cellClasses) }}" @if(isset($column['source']) && $column['source'] === 'Line') data-line-color="{{ $row->line_color ?? '#808080' }}" @endif>
                    @if(isset($column['highlight']))
                        <select class="form-select form-select-sm subcon-highlight-select" name="contracts[{{ $row->masterplan_id }}][highlight_color]" aria-label="Highlight {{ $row->CU }}">
                            <option value="" @selected(empty($row->highlight_color))>Không đánh dấu</option>
                            <option value="yellow" @selected(($row->highlight_color ?? '') === 'yellow')>Đang làm (vàng)</option>
                            <option value="green" @selected(($row->highlight_color ?? '') === 'green')>Hoàn thành (xanh)</option>
                        </select>
                    @elseif(isset($column['total']))
                        {{ number_format((float) ($row->Qty_dis ?? 0) * (float) ($row->unit_price ?? 0), 2) }}
                    @elseif(isset($column['field']))
                        @if($editableFields === null || in_array($column['field'], $editableFields, true))
                            <input class="form-control form-control-sm" type="{{ $column['input'] }}" name="contracts[{{ $row->masterplan_id }}][{{ $column['field'] }}]" value="{{ old('contracts.'.$row->masterplan_id.'.'.$column['field'], $row->{$column['field']} ?? '') }}" @if($column['input'] === 'number') step="0.01" min="0" @endif aria-label="{{ $column['label'] }} for {{ $row->CU }}">
                        @else
                            <input class="form-control form-control-sm" type="{{ $column['input'] }}" value="{{ $row->{$column['field']} ?? '' }}" @if($column['input'] === 'number') step="0.01" min="0" @endif readonly aria-label="{{ $column['label'] }} for {{ $row->CU }}">
                        @endif
                    @elseif(isset($column['date']))
                        {{ $row->{$column['source']} ? \Carbon\Carbon::parse($row->{$column['source']})->format('d/m/Y') : '' }}
                    @else
                        {{ $row->{$column['source']} ?? '' }}
                    @endif
                </td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">No manual Subcon Contract rows found.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@if($canEdit)
<div class="d-flex justify-content-end mt-2"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save All</button></div>
</form>
@endif
<div class="subcon-contract-scrollbar" aria-label="Cuộn ngang bảng Subcon Contract">
    <div class="subcon-contract-scrollbar-inner"></div>
</div>
<style>
    .subcon-contract-scroll { position: relative; overflow-x: auto !important; overflow-y: auto !important; width: 100%; max-width: 100%; max-height: calc(100vh - 220px); isolation: isolate; }
    .subcon-contract-scroll::-webkit-scrollbar:horizontal { height: 0; display: none; }
    .subcon-contract-table {
        table-layout: fixed;
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        --subcon-sticky-1: 155px;
        --subcon-sticky-2: 100px;
        --subcon-sticky-3: 125px;
        --subcon-sticky-4: 120px;
        --subcon-sticky-5: 125px;
        --subcon-sticky-6: 180px;
        --subcon-sticky-7: 75px;
    }
    .subcon-contract-table th { position: sticky; top: 0; z-index: 20; background: #f8f9fa; white-space: normal !important; word-break: keep-all; overflow-wrap: normal; line-height: 1.2; vertical-align: middle; }
    .subcon-contract-table td { overflow: hidden; text-overflow: ellipsis; }
    .subcon-contract-table td input { width: 100%; min-width: 0; padding-left: .35rem; padding-right: .35rem; }
    .subcon-contract-table td input[type=date] { min-width: 0; }
    .subcon-contract-table tr.subcon-highlight-yellow > td { background-color: #ffd54f !important; }
    .subcon-contract-table tr.subcon-highlight-green > td { background-color: #81c784 !important; }
    .subcon-contract-table .line-color-cell { text-align: center; color: #fff; font-weight: 500; border-radius: 4px; }
    .subcon-contract-table th.subcon-sticky-col,
    .subcon-contract-table td.subcon-sticky-col { position: sticky !important; }
    .subcon-contract-table td.subcon-sticky-col { z-index: 3; background-color: #fff; }
    .subcon-contract-table th.subcon-sticky-col { z-index: 30; background-color: #f8f9fa; }
    .subcon-contract-table tr.subcon-highlight-yellow > td.subcon-sticky-col { background-color: #ffd54f !important; }
    .subcon-contract-table tr.subcon-highlight-green > td.subcon-sticky-col { background-color: #81c784 !important; }
    .subcon-contract-table .subcon-sticky-1 { left: 0; }
    .subcon-contract-table .subcon-sticky-2 { left: var(--subcon-sticky-1); }
    .subcon-contract-table .subcon-sticky-3 { left: calc(var(--subcon-sticky-1) + var(--subcon-sticky-2)); }
    .subcon-contract-table .subcon-sticky-4 { left: calc(var(--subcon-sticky-1) + var(--subcon-sticky-2) + var(--subcon-sticky-3)); }
    .subcon-contract-table .subcon-sticky-5 { left: calc(var(--subcon-sticky-1) + var(--subcon-sticky-2) + var(--subcon-sticky-3) + var(--subcon-sticky-4)); }
    .subcon-contract-table .subcon-sticky-6 { left: calc(var(--subcon-sticky-1) + var(--subcon-sticky-2) + var(--subcon-sticky-3) + var(--subcon-sticky-4) + var(--subcon-sticky-5)); }
    .subcon-contract-table .subcon-sticky-7 { left: calc(var(--subcon-sticky-1) + var(--subcon-sticky-2) + var(--subcon-sticky-3) + var(--subcon-sticky-4) + var(--subcon-sticky-5) + var(--subcon-sticky-6)); }
    .subcon-contract-table .subcon-sticky-8 { left: calc(var(--subcon-sticky-1) + var(--subcon-sticky-2) + var(--subcon-sticky-3) + var(--subcon-sticky-4) + var(--subcon-sticky-5) + var(--subcon-sticky-6) + var(--subcon-sticky-7)); box-shadow: 2px 0 0 rgba(0, 0, 0, .12); }
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
        document.querySelectorAll('.subcon-highlight-select').forEach((select) => {
            select.addEventListener('change', () => {
                const row = select.closest('tr');
                row.classList.remove('subcon-highlight-yellow', 'subcon-highlight-green');
                if (select.value === 'yellow') row.classList.add('subcon-highlight-yellow');
                if (select.value === 'green') row.classList.add('subcon-highlight-green');
            });
        });
        syncWidth();
    })();
</script>
@endsection
