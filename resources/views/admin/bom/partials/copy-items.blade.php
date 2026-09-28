<template id="bomCopyToolbarTemplate">
    <div class="p-3 border-bottom d-flex flex-wrap align-items-center gap-2" id="bomCopyToolbar">
        <label class="d-flex align-items-center gap-2 me-2"><input type="checkbox" class="form-check-input m-0" id="bomSelectAll">Select all</label>
        <label for="bomCopyCount" class="mb-0">Copies per item</label>
        <input id="bomCopyCount" type="number" min="1" max="100" step="1" value="1" class="form-control form-control-sm" style="width:80px">
        <button id="bomCopySelected" type="button" class="btn btn-outline-primary btn-sm" disabled><i class="bi bi-copy me-1"></i>Copy selected (<span id="bomSelectedCount">0</span>)</button>
        <span class="small text-muted" id="bomCopyFeedback" role="status">Select items to copy, then edit the new rows before saving BOM.</span>
    </div>
</template>
<style>
    .bom-top-scroll {
        height: 16px;
        overflow-x: auto;
        overflow-y: hidden;
        background: #f8fafc;
        border-bottom: 1px solid #dee2e6;
        scrollbar-color: #94a3b8 #e2e8f0;
    }
    .bom-top-scroll.is-fixed {
        position: fixed;
        top: 0;
        z-index: 1050;
        box-shadow: 0 .15rem .3rem rgba(15, 23, 42, .18);
    }
    .bom-top-scroll-content { height: 1px; }
    .bom-top-scroll[hidden] { display: none; }
    .bom-items-responsive { scrollbar-width: none; }
    .bom-items-responsive::-webkit-scrollbar { display: none; }
    #itemsTable td.bom-copy-index-cell {
        min-width: 62px;
        white-space: nowrap;
    }
    .bom-copy-index-cell .form-check-input { margin: 0; flex: 0 0 auto; }
    .bom-copy-index-cell {
        display: table-cell;
        text-align: center;
    }
    .bom-copy-index-cell > input[data-bom-copy-select] {
        vertical-align: middle;
        margin-right: 5px !important;
    }
    .bom-copy-row-number {
        display: inline-flex;
        min-width: 1.5rem;
        height: 1.5rem;
        padding: 0 .25rem;
        align-items: center;
        justify-content: center;
        border: 1px solid #cbd5e1;
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
        font-size: .75rem;
        font-weight: 700;
        line-height: 1;
        vertical-align: middle;
    }
</style>
<script type="application/json" id="bomOldItems" data-max-inputs="{{ (int) ini_get('max_input_vars') }}">@json(old('items'))</script>
<script src="{{ asset('js/bom-item-copy.js') }}?v={{ filemtime(public_path('js/bom-item-copy.js')) }}" defer></script>
