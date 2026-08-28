@extends('layouts.app')
@section('title', 'Edit BOM - ' . $bom->style_no)
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

    <form method="POST" action="{{ route('admin.bom.update', $bom->id) }}" id="bomForm">
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
                        <label class="form-label">Style No <span class="text-danger">*</span></label>
                        <input type="text" name="style_no" class="form-control" value="{{ old('style_no', $bom->style_no) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Style Name</label>
                        <input type="text" name="style_name" class="form-control" value="{{ old('style_name', $bom->style_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer</label>
                        <input type="text" name="customer" class="form-control" value="{{ old('customer', $bom->customer) }}">
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
                            <th>Code *</th>
                            <th>Description *</th>
                            <th>Type</th>
                            <th>Colour</th>
                            <th>Size</th>
                            <th>Width</th>
                            <th>Unit</th>
                            <th>Yield</th>
                            <th>Waste %</th>
                            <th>Unit Price</th>
                            <th>Remark</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        @foreach($items as $i => $item)
                        <tr id="itemRow{{ $i }}">
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td><input type="text" name="items[{{ $i }}][material_code]" class="form-control form-control-sm" required value="{{ $item->material_code }}"></td>
                            <td><input type="text" name="items[{{ $i }}][material_name]" class="form-control form-control-sm" required value="{{ $item->material_name }}"></td>
                            <td>
                                <select name="items[{{ $i }}][material_type]" class="form-select form-select-sm">
                                    @foreach(['fabric','lining','pocket','trim','thread','zipper','label','elastic','interlining','other'] as $type)
                                        <option value="{{ $type }}" {{ $item->material_type === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="items[{{ $i }}][colour]" class="form-control form-control-sm" value="{{ $item->colour }}"></td>
                            <td><input type="text" name="items[{{ $i }}][size]" class="form-control form-control-sm" value="{{ $item->size }}"></td>
                            <td><input type="number" step="0.01" name="items[{{ $i }}][width]" class="form-control form-control-sm" value="{{ $item->width }}"></td>
                            <td>
                                <select name="items[{{ $i }}][unit]" class="form-select form-select-sm">
                                    @foreach(['M','YD','KG','PCS','SET','PR','ROLL'] as $unit)
                                        <option value="{{ $unit }}" {{ $item->unit === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.0001" min="0.0001" name="items[{{ $i }}][consumption_rate]" class="form-control form-control-sm" required value="{{ $item->consumption_rate }}"></td>
                            <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][waste_percent]" class="form-control form-control-sm" value="{{ $item->waste_percent }}"></td>
                            <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_cost]" class="form-control form-control-sm" value="{{ $item->unit_cost }}"></td>
                            <td><input type="text" name="items[{{ $i }}][remark]" class="form-control form-control-sm" value="{{ $item->remark }}"></td>
                            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)"><i class="bi bi-x"></i></button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.bom.show', $bom->id) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Update BOM</button>
        </div>
    </form>
</div>

<script>
let itemCount = {{ count($items) }};

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
            <td><input type="text" name="items[${i}][material_code]" class="form-control form-control-sm" required value="${data.code || ''}"></td>
            <td><input type="text" name="items[${i}][material_name]" class="form-control form-control-sm" required value="${data.name || ''}"></td>
            <td>
                <select name="items[${i}][material_type]" class="form-select form-select-sm">
                    ${materialTypes.map(t => `<option value="${t.value}" ${(data.type || 'fabric') === t.value ? 'selected' : ''}>${t.label}</option>`).join('')}
                </select>
            </td>
            <td><input type="text" name="items[${i}][colour]" class="form-control form-control-sm" value="${data.colour || ''}"></td>
            <td><input type="text" name="items[${i}][size]" class="form-control form-control-sm" value="${data.size || ''}"></td>
            <td><input type="number" step="0.01" name="items[${i}][width]" class="form-control form-control-sm" value="${data.width || ''}"></td>
            <td>
                <select name="items[${i}][unit]" class="form-select form-select-sm">
                    <option value="M" ${data.unit === 'M' ? 'selected' : ''}>M</option>
                    <option value="YD" ${data.unit === 'YD' ? 'selected' : ''}>YD</option>
                    <option value="KG" ${data.unit === 'KG' ? 'selected' : ''}>KG</option>
                    <option value="PCS" ${data.unit === 'PCS' ? 'selected' : ''}>PCS</option>
                    <option value="SET" ${data.unit === 'SET' ? 'selected' : ''}>SET</option>
                    <option value="PR" ${data.unit === 'PR' ? 'selected' : ''}>PR</option>
                    <option value="ROLL" ${data.unit === 'ROLL' ? 'selected' : ''}>ROLL</option>
                </select>
            </td>
            <td><input type="number" step="0.0001" min="0.0001" name="items[${i}][consumption_rate]" class="form-control form-control-sm" required value="${data.yield || ''}"></td>
            <td><input type="number" step="0.01" min="0" name="items[${i}][waste_percent]" class="form-control form-control-sm" value="${data.waste || ''}"></td>
            <td><input type="number" step="0.01" min="0" name="items[${i}][unit_cost]" class="form-control form-control-sm" value="${data.cost || ''}"></td>
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
        row.querySelector('td:first-child').textContent = idx + 1;
    });
}
</script>

@endsection
