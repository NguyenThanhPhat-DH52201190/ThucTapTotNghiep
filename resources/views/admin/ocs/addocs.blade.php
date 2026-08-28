@extends('layouts.app')
@section('title', 'Add Order Cutsheet')
@section('content')

<div class="container-fluid px-0">
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-plus-lg me-2"></i>Add Order Cutsheet</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.ocs.store') }}">
                @csrf
                <div class="row g-3">
                    <!-- Core fields -->
                    <div class="col-md-4">
                        <label class="form-label">CS <span class="text-danger">*</span></label>
                        <input type="text" name="CS" class="form-control" value="{{ old('CS') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CsDate <span class="text-danger">*</span></label>
                        <input type="date" name="CsDate" class="form-control" value="{{ old('CsDate') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ONum (PO) <span class="text-danger">*</span></label>
                        <input type="text" name="ONum" class="form-control" value="{{ old('ONum') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SNo (Style) <span class="text-danger">*</span></label>
                        <input type="text" name="SNo" class="form-control" value="{{ old('SNo') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SName <span class="text-danger">*</span></label>
                        <input type="text" name="Sname" class="form-control" value="{{ old('Sname') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer master</label>
                        <select name="customer_id" id="customerMaster" class="form-select"><option value="">-- Select customer or enter manually --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-name="{{ $customer->name }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}{{ $customer->brand ? ' · '.$customer->brand : '' }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer name <span class="text-danger">*</span></label>
                        <input type="text" id="customerName" name="Customer" class="form-control" value="{{ old('Customer') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Color <span class="text-danger">*</span></label>
                        <input type="text" name="Color" class="form-control" value="{{ old('Color') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Qty <span class="text-danger">*</span></label>
                        <input type="number" name="Qty" class="form-control" value="{{ old('Qty') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">CMT</label>
                        <input type="number" name="CMT" step="0.01" class="form-control" value="{{ old('CMT') }}">
                    </div>
                    <div class="col-md-2"><label class="form-label">Order Type</label><select name="order_type" class="form-select"><option value="cmt" {{ old('order_type','cmt')==='cmt'?'selected':'' }}>CMT</option><option value="fob" {{ old('order_type')==='fob'?'selected':'' }}>FOB</option></select></div>
                    <div class="col-md-3"><label class="form-label">Material Owner</label><select name="material_ownership" class="form-select"><option value="factory" {{ old('material_ownership','factory')==='factory'?'selected':'' }}>Factory</option><option value="customer" {{ old('material_ownership')==='customer'?'selected':'' }}>Customer</option></select></div>
                    <div class="col-md-3"><label class="form-label">FOB Unit Price</label><input type="number" step="0.01" min="0" name="unit_price" class="form-control" value="{{ old('unit_price',0) }}"></div>

                    <!-- New Order Management Fields -->
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>⏳ Pending</option>
                            <option value="confirmed" {{ old('status') == 'confirmed' ? 'selected' : '' }}>✅ Confirmed</option>
                            <option value="released" {{ old('status') == 'released' ? 'selected' : '' }}>Released</option>
                            <option value="in_production" {{ old('status') == 'in_production' ? 'selected' : '' }}>🏭 In Production</option>
                            <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>✔ Completed</option>
                            <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>❌ Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>🟢 Low</option>
                            <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>🟡 Medium</option>
                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>🟠 High</option>
                            <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>🔴 Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expected Ship Date</label>
                        <input type="date" name="expected_ship_date" class="form-control" value="{{ old('expected_ship_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">BOM (Bill of Materials)</label>
                        <select name="bom_header_id" class="form-select">
                            <option value="">-- Select BOM --</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" {{ old('bom_header_id') == $bom->id ? 'selected' : '' }}>
                                    {{ $bom->style_no }} - {{ $bom->style_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Order Notes</label>
                        <textarea name="order_notes" class="form-control" rows="2">{{ old('order_notes') }}</textarea>
                    </div>
                </div>

                <div class="card border mt-4">
                    <div class="card-header d-flex justify-content-between"><strong>Size breakdown</strong><button type="button" class="btn btn-sm btn-outline-primary" onclick="addSizeRow()">Add size</button></div>
                    <div class="card-body" id="sizeRows"><div class="row g-2 size-row"><div class="col-md-5"><input name="sizes[0][size_name]" class="form-control" placeholder="Size (S, M, L...)" required></div><div class="col-md-5"><input type="number" min="0" name="sizes[0][quantity]" class="form-control" placeholder="Quantity" required></div></div></div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save</button>
                    <a href="{{ route('admin.ocs.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@push('scripts')
<script>let sizeIndex=1; function addSizeRow(){document.getElementById('sizeRows').insertAdjacentHTML('beforeend', `<div class="row g-2 size-row mt-2"><div class="col-md-5"><input name="sizes[${sizeIndex}][size_name]" class="form-control" placeholder="Size" required></div><div class="col-md-5"><input type="number" min="0" name="sizes[${sizeIndex}][quantity]" class="form-control" placeholder="Quantity" required></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger" onclick="this.closest('.size-row').remove()">Remove</button></div></div>`);sizeIndex++;}document.getElementById('customerMaster')?.addEventListener('change',function(){const option=this.options[this.selectedIndex];if(option.dataset.name)document.getElementById('customerName').value=option.dataset.name;});</script>
@endpush
