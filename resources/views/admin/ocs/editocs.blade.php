@extends('layouts.app')
@section('title', 'Edit Order Cutsheet')
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
            <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Order: {{ $order->CS }}</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.ocs.update', $order->id) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="row g-3">
                    @include('admin.partials.customer-style-selector')
                    <div class="col-md-4">
                        <label class="form-label">CS <span class="text-danger">*</span></label>
                        <input type="text" name="CS" class="form-control" value="{{ old('CS', $order->CS) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CsDate <span class="text-danger">*</span></label>
                        <input type="date" name="CsDate" class="form-control" value="{{ old('CsDate', $order->CsDate) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ONum (PO) <span class="text-danger">*</span></label>
                        <input type="text" name="ONum" class="form-control" value="{{ old('ONum', $order->ONum) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">BOM (Bill of Materials)</label>
                        <select name="bom_header_id" id="bomHeader" class="form-select">
                            <option value="">-- Select BOM --</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" data-customer="{{ $bom->customer_id }}" data-style-no="{{ $bom->style_no }}" data-style-name="{{ $bom->style_name }}" {{ old('bom_header_id', $order->selected_template_id) == $bom->id ? 'selected' : '' }}>
                                    {{ $bom->style_no }} - {{ $bom->style_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SNo (Style) <span class="text-danger">*</span></label>
                        <select name="SNo" id="styleNo" class="form-select" data-customer-style data-current="{{ old('SNo', $order->SNo ?? '') }}" required><option value="">-- Select Style --</option></select>SNo) }}" required placeholder="Enter style code">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SName <span class="text-danger">*</span></label>
                        <input type="text" id="styleName" name="Sname" class="form-control" value="{{ old('Sname', $order->Sname) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer master</label>
                        <select name="customer_id" id="customerMaster" class="form-select"><option value="">-- Select customer --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-sizes='@json(($customerSizes[$customer->id] ?? collect())->pluck("size_name")->values())' @selected(old('customer_id', $order->customer_id) == $customer->id)>{{ $customer->name }}{{ $customer->brand ? ' · '.$customer->brand : '' }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer name <span class="text-danger">*</span></label>
                        <input type="text" id="customerName" name="Customer" class="form-control" value="{{ old('Customer', $order->Customer) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Color <span class="text-danger">*</span></label>
                        <input type="text" name="Color" class="form-control" value="{{ old('Color', $order->Color) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Qty <span class="text-danger">*</span></label>
                        <input type="number" name="Qty" class="form-control" value="{{ old('Qty', $order->Qty) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">CMT</label>
                        <input type="number" step="0.01" name="CMT" class="form-control" value="{{ old('CMT', $order->CMT) }}">
                    </div>

                    <!-- Order Management Fields -->
                    <div class="col-md-4">
                        <label class="form-label">Status (managed by workflow)</label>
                        <input class="form-control" value="{{ strtoupper(str_replace('_', ' ', $order->status ?? 'pending')) }}" readonly>
                    </div>
                    <div class="col-md-2"><label class="form-label">Order Type</label><select name="order_type" class="form-select"><option value="cmt" {{ old('order_type',$order->order_type??'cmt')==='cmt'?'selected':'' }}>CMT</option><option value="fob" {{ old('order_type',$order->order_type??'cmt')==='fob'?'selected':'' }}>FOB</option></select></div>
                    <div class="col-md-3"><label class="form-label">Material Owner</label><select name="material_ownership" class="form-select"><option value="factory" {{ old('material_ownership',$order->material_ownership??'factory')==='factory'?'selected':'' }}>Factory</option><option value="customer" {{ old('material_ownership',$order->material_ownership??'factory')==='customer'?'selected':'' }}>Customer</option></select></div>
                    <div class="col-md-3"><label class="form-label">FOB Unit Price</label><input type="number" step="0.0001" min="0" name="unit_price" class="form-control" value="{{ old('unit_price', number_format((float) ($order->unit_price ?? 0), 4, '.', '')) }}"></div>
                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low" {{ old('priority', $order->priority) == 'low' ? 'selected' : '' }}>🟢 Low</option>
                            <option value="medium" {{ old('priority', $order->priority) == 'medium' ? 'selected' : '' }}>🟡 Medium</option>
                            <option value="high" {{ old('priority', $order->priority) == 'high' ? 'selected' : '' }}>🟠 High</option>
                            <option value="urgent" {{ old('priority', $order->priority) == 'urgent' ? 'selected' : '' }}>🔴 Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expected Ship Date</label>
                        <input type="date" name="expected_ship_date" class="form-control" value="{{ old('expected_ship_date', $order->expected_ship_date) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Order Notes</label>
                        <textarea name="order_notes" class="form-control" rows="2">{{ old('order_notes', $order->order_notes) }}</textarea>
                    </div>
                </div>

                <div class="card border mt-4"><div class="card-header"><strong>Size breakdown by customer</strong></div><div class="card-body" id="sizeRows">@foreach(old('sizes', $sizes->map(fn($size) => ['size_name'=>$size->size_name,'quantity'=>$size->quantity])->all()) as $index => $size)<div class="row g-2 size-row {{ $index ? 'mt-2' : '' }}"><div class="col-md-5"><select name="sizes[{{ $index }}][size_name]" class="form-select customer-size-select" data-current="{{ $size['size_name'] }}" required></select></div><div class="col-md-5"><input type="number" min="0" name="sizes[{{ $index }}][quantity]" class="form-control" value="{{ $size['quantity'] }}" required></div></div>@endforeach</div></div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Update</button>
                    <a href="{{ route('admin.ocs.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@push('scripts')
<script>
const customerMaster = document.getElementById('customerMaster');
function customerSizeNames(){const option=customerMaster.options[customerMaster.selectedIndex];try{return JSON.parse(option?.dataset.sizes||'[]');}catch{return [];}}
function renderCustomerSizes(reset=false){const sizes=customerSizeNames(),box=document.getElementById('sizeRows');if(reset||!box.querySelector('.size-row')){box.innerHTML=sizes.length?sizes.map((size,i)=>`<div class="row g-2 size-row ${i?'mt-2':''}"><div class="col-md-5"><input class="form-control" value="${size}" readonly><input type="hidden" name="sizes[${i}][size_name]" value="${size}"></div><div class="col-md-5"><input type="number" min="0" name="sizes[${i}][quantity]" class="form-control" value="0" required></div></div>`).join(''):'<div class="text-muted">Configure sizes for this customer in Customer Size Breakdown first.</div>';return;}box.querySelectorAll('.customer-size-select').forEach(select=>{const current=select.dataset.current;const values=sizes.includes(current)?sizes:[current,...sizes].filter(Boolean);select.innerHTML=values.map(size=>`<option value="${size}" ${size===current?'selected':''}>${size}</option>`).join('');});}
customerMaster?.addEventListener('change',function(){const option=this.options[this.selectedIndex];if(option.dataset.name)document.getElementById('customerName').value=option.dataset.name;renderCustomerSizes(true);});
renderCustomerSizes(false);



</script>
@endpush
