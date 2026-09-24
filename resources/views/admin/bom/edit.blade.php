@extends('layouts.app')
@section('title', 'Edit BOM - ' . $bom->style_no)
@section('content')
@include('admin.partials.customer-style-selector')

<div class="container-fluid px-0">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bom.update', $bom->id) }}" id="bomForm" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-file-text me-2"></i>Edit BOM: {{ $bom->style_no }}</h5>
                <span class="badge bg-{{ $bom->status === 'active' ? 'success' : ($bom->status === 'draft' ? 'warning' : 'secondary') }}">
                    {{ ucfirst($bom->status) }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Customer Master <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_id" class="form-select" required>
                            <option value="">-- Select customer --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $bom->customer_id) === (string) $customer->id)>
                                    {{ $customer->name }}{{ $customer->brand ? ' — ' . $customer->brand : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Current display name: {{ $bom->customer ?: 'Not mapped' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Style No <span class="text-danger">*</span></label>
                        <select name="style_no" id="style_no" class="form-select" data-customer-style data-current="{{ old('style_no', $bom->style_no ?? '') }}" required><option value="">-- Select Style --</option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Style Name</label>
                        <input type="text" name="style_name" class="form-control" value="{{ old('style_name', $bom->style_name) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Version</label>
                        <input type="text" name="version" class="form-control" value="{{ old('version', $bom->version) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" {{ ($bom->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="active" {{ $bom->status === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="archived" {{ $bom->status === 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Effective Date</label>
                        <input type="date" name="effective_date" class="form-control"
                               value="{{ old('effective_date', $bom->effective_date) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $bom->notes) }}</textarea>
                    </div>
                    <div class="col-md-6"><label class="form-label">Change reason <span class="text-danger">*</span></label><input name="change_reason" class="form-control" maxlength="255" value="{{ old('change_reason') }}" required></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>BOM Items</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="addItem()">
                    <i class="bi bi-plus-lg"></i> Add Item
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Type</th>
                            <th>Code *</th>
                            <th>Description *</th>
                            <th>Colour</th>
                            <th>Material Size</th>
                            <th style="min-width:190px">Apply To Product Sizes</th>
                            <th>Width</th>
                            <th>Unit</th>
                            <th>Yield</th>
                            <th>Waste %</th>
                            <th>Remark</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        @foreach($items as $i => $item)
                        <tr id="itemRow{{ $i }}">
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>
                                <select name="items[{{ $i }}][category_id]" class="form-select form-select-sm material-category-select" required>
                                    <option value="">Select type</option>
                                    @foreach($materialCategories as $category)
                                        <option value="{{ $category->id }}" @selected((string) ($materialCategoryIds[$item->material_id] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}"><input type="text" name="items[{{ $i }}][material_code]" class="form-control form-control-sm material-code-input" required autocomplete="off" value="{{ $item->material_code }}"></td>
                            <td><input type="text" name="items[{{ $i }}][material_name]" class="form-control form-control-sm material-description-input" required readonly value="{{ $item->material_name }}"></td>
                            <td><input type="text" name="items[{{ $i }}][colour]" class="form-control form-control-sm material-colour-input material-master-input" value="{{ $item->colour }}" readonly></td>
                            <td><input type="text" name="items[{{ $i }}][size]" class="form-control form-control-sm material-size-input material-master-input" value="{{ $item->size }}" readonly></td>
                            <td>
                                @php($selectedSizes = ($itemSizeMappings[$item->id] ?? collect())->map(fn($id) => (string) $id))
                                <select multiple name="items[{{ $i }}][customer_size_ids][]" class="form-select form-select-sm product-size-select">
                                    <option value="all" @selected($selectedSizes->isEmpty())>All product sizes</option>
                                    @foreach(($customerSizes[$bom->customer_id] ?? collect()) as $productSize)
                                        <option value="{{ $productSize->id }}" @selected($selectedSizes->contains((string) $productSize->id))>{{ $productSize->size_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Ctrl/Cmd to select multiple</small>
                            </td>
                            <td><input type="number" step="0.01" name="items[{{ $i }}][width]" class="form-control form-control-sm" value="{{ $item->width }}"></td>
                            <td>
                                <input type="text" name="items[{{ $i }}][unit]" class="form-control form-control-sm material-unit-input" value="{{ $item->unit }}" readonly>
                            </td>
                            <td><input type="number" step="0.0001" min="0.0001" name="items[{{ $i }}][consumption_rate]" class="form-control form-control-sm" required value="{{ $item->consumption_rate }}"></td>
                            <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][waste_percent]" class="form-control form-control-sm" value="{{ $item->waste_percent }}"></td>
                            <td><input type="text" name="items[{{ $i }}][remark]" class="form-control form-control-sm" value="{{ $item->remark }}"></td>
                            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)"><i class="bi bi-x"></i></button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted"><small><i class="bi bi-info-circle"></i> Material Size is the material specification; Product Sizes come from the selected customer's Size Breakdown.</small></div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.bom.show', $bom->id) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Update BOM</button>
        </div>
    </form>
</div>

<script>
let itemCount = {{ count($items) }};
const customerSizes = @json($customerSizes);
const materialCategories = @json($materialCategories);

function materialCategoryOptions(selected = '') {
    return `<option value="">Select type</option>` + materialCategories.map(category =>
        `<option value="${category.id}" ${String(selected) === String(category.id) ? 'selected' : ''}>${category.name}</option>`
    ).join('');
}

function productSizeOptions(selected = []) {
    const customerId = document.getElementById('customer_id').value;
    const sizes = customerSizes[customerId] || [];
    const values = (selected || []).map(String);
    const allSelected = values.length === 0 || values.includes('all');
    return `<option value="all" ${allSelected ? 'selected' : ''}>All product sizes</option>` +
        sizes.map(size => `<option value="${size.id}" ${values.includes(String(size.id)) ? 'selected' : ''}>${size.size_name}</option>`).join('');
}

function refreshProductSizes() {
    document.querySelectorAll('.product-size-select').forEach(select => select.innerHTML = productSizeOptions([]));
}

function addItem(data = {}) {
    itemCount++;
    const i = itemCount;
    const html = `
        <tr id="itemRow${i}">
            <td class="text-center">${i}</td>
            <td>
                <select name="items[${i}][category_id]" class="form-select form-select-sm material-category-select" required>
                    ${materialCategoryOptions(data.categoryId || '')}
                </select>
            </td>
            <td><input type="text" name="items[${i}][material_code]" class="form-control form-control-sm material-code-input" required autocomplete="off" value="${data.code || ''}" placeholder="Type to search Material Master"></td>
            <td><input type="text" name="items[${i}][material_name]" class="form-control form-control-sm material-description-input" required readonly value="${data.name || ''}" placeholder="Selected material name"></td>
            <td><input type="text" name="items[${i}][colour]" class="form-control form-control-sm material-colour-input material-master-input" value="${data.colour || ''}" readonly></td>
            <td><input type="text" name="items[${i}][size]" class="form-control form-control-sm material-size-input material-master-input" value="${data.size || ''}" readonly></td>
            <td>
                <select multiple name="items[${i}][customer_size_ids][]" class="form-select form-select-sm product-size-select">
                    ${productSizeOptions(data.customerSizeIds || [])}
                </select>
                <small class="text-muted">Ctrl/Cmd to select multiple</small>
            </td>
            <td><input type="number" step="0.01" name="items[${i}][width]" class="form-control form-control-sm" value="${data.width || ''}"></td>
            <td>
                <input type="text" name="items[${i}][unit]" class="form-control form-control-sm material-unit-input" value="${data.unit || ''}" placeholder="Unit" readonly>
            </td>
            <td><input type="number" step="0.0001" min="0.0001" name="items[${i}][consumption_rate]" class="form-control form-control-sm" required value="${data.yield || ''}"></td>
            <td><input type="number" step="0.01" min="0" name="items[${i}][waste_percent]" class="form-control form-control-sm" value="${data.waste || ''}"></td>
            <td><input type="text" name="items[${i}][remark]" class="form-control form-control-sm" value="${data.remark || ''}"></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)"><i class="bi bi-x"></i></button></td>
        </tr>
    `;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', html);
}

function removeItem(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('#itemsBody tr').length > 1) {
        row.remove();
        renumberItems();
    } else {
        alert('Need at least 1 item');
    }
}

function renumberItems() {
    document.querySelectorAll('#itemsBody tr').forEach((row, idx) => {
        (row.querySelector('[data-bom-row-number]') || row.querySelector('td:first-child')).textContent = idx + 1;
    });
}

document.getElementById('customer_id').addEventListener('change', refreshProductSizes);
document.addEventListener('change', function(event) {
    if (!event.target.classList.contains('product-size-select')) return;
    const all = event.target.querySelector('option[value="all"]');
    if (all?.selected && event.target.selectedOptions.length > 1) all.selected = false;
});
</script>

@include('admin.bom.partials.material-autocomplete')
@include('admin.bom.partials.copy-items')

@endsection
