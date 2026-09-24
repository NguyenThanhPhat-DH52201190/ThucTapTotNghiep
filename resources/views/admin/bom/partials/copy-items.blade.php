<template id="bomCopyToolbarTemplate">
    <div class="p-3 border-bottom d-flex flex-wrap align-items-center gap-2" id="bomCopyToolbar">
        <label class="d-flex align-items-center gap-2 me-2"><input type="checkbox" class="form-check-input m-0" id="bomSelectAll">Select all</label>
        <label for="bomCopyCount" class="mb-0">Copies per item</label>
        <input id="bomCopyCount" type="number" min="1" max="100" step="1" value="1" class="form-control form-control-sm" style="width:80px">
        <button id="bomCopySelected" type="button" class="btn btn-outline-primary btn-sm" disabled><i class="bi bi-copy me-1"></i>Copy selected (<span id="bomSelectedCount">0</span>)</button>
        <span class="small text-muted" id="bomCopyFeedback" role="status">Select items to copy, then edit the new rows before saving BOM.</span>
    </div>
</template>
<script type="application/json" id="bomOldItems" data-max-inputs="{{ (int) ini_get('max_input_vars') }}">@json(old('items'))</script>
<script src="{{ asset('js/bom-item-copy.js') }}?v={{ filemtime(public_path('js/bom-item-copy.js')) }}" defer></script>
