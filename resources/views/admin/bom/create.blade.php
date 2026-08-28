@extends('layouts.app')
@section('title', 'Create BOM')
@section('content')

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

    <form method="POST" action="{{ route('admin.bom.store') }}" id="bomForm">
        @csrf

        <!-- Header Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-file-text me-2"></i>Create New BOM</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Style No <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="style_no" id="style_no" class="form-control" list="styleList" required
                                   value="{{ old('style_no') }}" placeholder="Type or select style">
                            <datalist id="styleList">
                                @foreach($styles as $s)
                                    <option value="{{ $s->SNo }}" data-name="{{ $s->Sname }}" data-customer="{{ $s->Customer }}">
                                @endforeach
                            </datalist>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Style Name</label>
                        <input type="text" name="style_name" id="style_name" class="form-control" value="{{ old('style_name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer</label>
                        <input type="text" name="customer" id="customer" class="form-control" value="{{ old('customer') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Version</label>
                        <input type="text" name="version" class="form-control" value="{{ old('version', 'V1') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Effective Date</label>
                        <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOM Items Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>BOM Items (Materials)</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="addItem()">
                    <i class="bi bi-plus-lg"></i> Add Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Code <span class="text-danger">*</span></th>
                                <th>Description <span class="text-danger">*</span></th>
                                <th>Type</th>
                                <th>Colour</th>
                                <th>Size</th>
                                <th>Width</th>
                                <th>Unit</th>
                                <th>Yield (ĐM)</th>
                                <th>Waste %</th>
                                <th>Unit Price</th>
                                <th>Remark</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- Items will be added via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted">
                <small><i class="bi bi-info-circle"></i> Tip: You can copy-paste from Excel directly into the Code field</small>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.bom.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save BOM</button>
        </div>
    </form>
</div>

<script>
let itemCount = 0;

const materialTypes = [
    { value: 'fabric', label: 'Fabric (Vải chính)' },
    { value: 'lining', label: 'Lining (Vải lót)' },
    { value: 'pocket', label: 'Pocket (Túi)' },
    { value: 'trim', label: 'Trim (Phụ liệu)' },
    { value: 'thread', label: 'Thread (Chỉ)' },
    { value: 'zipper', label: 'Zipper (Dây kéo)' },
    { value: 'label', label: 'Label (Nhãn)' },
    { value: 'elastic', label: 'Elastic (Thun)' },
    { value: 'interlining', label: 'Interlining (Keo)' },
    { value: 'other', label: 'Other (Khác)' },
];

function addItem(data = {}) {
    itemCount++;
    const i = itemCount;
    const html = `
        <tr id="itemRow${i}">
            <td class="text-center">${i}</td>
            <td>
                <input type="text" name="items[${i}][material_code]" class="form-control form-control-sm" required
                       value="${data.code || ''}" placeholder="EX-V-TPU-180306">
            </td>
            <td>
                <input type="text" name="items[${i}][material_name]" class="form-control form-control-sm" required
                       value="${data.name || ''}" placeholder="Material description">
            </td>
            <td>
                <select name="items[${i}][material_type]" class="form-select form-select-sm">
                    ${materialTypes.map(t =>
                        `<option value="${t.value}" ${(data.type || 'fabric') === t.value ? 'selected' : ''}>${t.label}</option>`
                    ).join('')}
                </select>
            </td>
            <td>
                <input type="text" name="items[${i}][colour]" class="form-control form-control-sm"
                       value="${data.colour || ''}" placeholder="Colour">
            </td>
            <td>
                <input type="text" name="items[${i}][size]" class="form-control form-control-sm"
                       value="${data.size || ''}" placeholder="Size">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${i}][width]" class="form-control form-control-sm"
                       value="${data.width || ''}" placeholder="Width">
            </td>
            <td>
                <select name="items[${i}][unit]" class="form-select form-select-sm">
                    <option value="M" ${data.unit === 'M' ? 'selected' : ''}>M (Meter)</option>
                    <option value="YD" ${data.unit === 'YD' ? 'selected' : ''}>YD (Yard)</option>
                    <option value="KG" ${data.unit === 'KG' ? 'selected' : ''}>KG</option>
                    <option value="PCS" ${data.unit === 'PCS' ? 'selected' : ''}>PCS</option>
                    <option value="SET" ${data.unit === 'SET' ? 'selected' : ''}>SET</option>
                    <option value="PR" ${data.unit === 'PR' ? 'selected' : ''}>PR (Pair)</option>
                    <option value="ROLL" ${data.unit === 'ROLL' ? 'selected' : ''}>ROLL</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.0001" min="0.0001" name="items[${i}][consumption_rate]" class="form-control form-control-sm" required
                       value="${data.yield || ''}" placeholder="0.0000">
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${i}][waste_percent]" class="form-control form-control-sm"
                       value="${data.waste || ''}" placeholder="0">
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${i}][unit_cost]" class="form-control form-control-sm"
                       value="${data.cost || ''}" placeholder="0">
            </td>
            <td>
                <input type="text" name="items[${i}][remark]" class="form-control form-control-sm"
                       value="${data.remark || ''}" placeholder="Remark">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${i})">
                    <i class="bi bi-x"></i>
                </button>
            </td>
        </tr>
    `;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', html);
}

function removeItem(id) {
    const row = document.getElementById('itemRow' + id);
    if (row && document.querySelectorAll('#itemsBody tr').length > 1) {
        row.remove();
        renumberItems();
    } else {
        alert('Need at least 1 item');
    }
}

function renumberItems() {
    const rows = document.querySelectorAll('#itemsBody tr');
    rows.forEach((row, idx) => {
        row.querySelector('td:first-child').textContent = idx + 1;
    });
}

// Auto-fill style info when selecting from datalist
document.addEventListener('DOMContentLoaded', function() {
    const styleInput = document.getElementById('style_no');
    styleInput.addEventListener('change', function() {
        const datalist = document.getElementById('styleList');
        const options = datalist.options;
        for (let opt of options) {
            if (opt.value === this.value) {
                document.getElementById('style_name').value = opt.dataset.name || '';
                document.getElementById('customer').value = opt.dataset.customer || '';
                break;
            }
        }
    });
});

</script>

@endsection
