@extends('layouts.app')
@section('title', 'Import BOM Preview')
@section('content')
@include('admin.partials.customer-style-selector')

<div class="container-fluid px-0">
    <form method="POST" action="{{ route('admin.bom.store') }}" id="importForm">
        @csrf

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-excel me-2"></i>Import BOM from Excel - Preview</h5>
                <span class="badge bg-primary">{{ count($parsedItems) }} items detected</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Style No <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="style_no" id="style_no" class="form-select" data-customer-style data-current="{{ old('style_no', $bom->style_no ?? '') }}" required><option value="">-- Select Style --</option></select>
                            
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Style Name</label>
                        <input type="text" name="style_name" id="style_name" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer Master</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">-- Select customer --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->brand ? ' — ' . $customer->brand : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Version</label>
                        <input type="text" name="version" class="form-control" value="V1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Effective Date</label>
                        <input type="date" name="effective_date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">Imported from Excel on {{ now()->format('d/m/Y') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-table me-2"></i>Preview Items
                    <small class="text-muted ms-2">(Review and adjust before saving)</small>
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Colour</th>
                            <th>Size</th>
                            <th>Width</th>
                            <th>Unit</th>
                            <th>Yield (ĐM)</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody id="previewBody">
                        @foreach($parsedItems as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][material_code]" value="{{ $item['material_code'] }}">
                                <code>{{ $item['material_code'] }}</code>
                            </td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][material_name]" value="{{ $item['material_name'] }}">
                                {{ $item['material_name'] }}
                            </td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][material_type]" value="{{ $item['material_type'] }}">
                                <input type="hidden" name="items[{{ $i }}][category_id]" value="{{ $item['category_id'] }}">
                                {{ ucfirst($item['material_type']) }}
                            </td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][colour]" value="{{ $item['colour'] }}">
                                {{ $item['colour'] ?: '-' }}
                            </td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][size]" value="{{ $item['size'] }}">
                                {{ $item['size'] ?: '-' }}
                            </td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][width]" value="{{ $item['width'] }}">
                                {{ $item['width'] ?: '-' }}
                            </td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][unit]" value="{{ $item['unit'] }}">
                                {{ $item['unit'] }}
                            </td>
                            <td>
                                <input type="number" step="0.0001" min="0.0001" name="items[{{ $i }}][consumption_rate]" class="form-control form-control-sm" required
                                       value="{{ $item['consumption_rate'] }}" style="width:100px">
                            </td>
                            <td>
                                <input type="text" name="items[{{ $i }}][remark]" class="form-control form-control-sm"
                                       value="{{ $item['remark'] }}" style="min-width:150px">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.bom.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save BOM with {{ count($parsedItems) }} items</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

});
</script>

@endsection
