@extends('layouts.app')
@section('title', 'Customer Size Breakdown')
@section('content')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card shadow-sm border-0 mb-4"><div class="card-body"><h5 class="mb-1 fw-bold"><i class="bi bi-rulers me-2"></i>Customer Size Breakdown</h5><small class="text-muted">Define the sizes available when creating an OCS for each customer.</small></div></div>
    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Customer</th><th>Brand</th><th>Sizes</th><th class="text-end">Action</th></tr></thead>
        <tbody>@foreach($customers as $customer) @php($customerSizes = ($sizes[$customer->id] ?? collect())->pluck('size_name'))
            <tr><td class="fw-semibold">{{ $customer->name }}</td><td>{{ $customer->brand ?: '-' }}</td><td>@forelse($customerSizes as $size)<span class="badge bg-primary me-1">{{ $size }}</span>@empty<span class="text-muted">No sizes configured</span>@endforelse</td>
            <td class="text-end"><button class="btn btn-sm btn-warning" onclick='editSizes(@json($customer), @json($customerSizes->values()))'><i class="bi bi-pencil"></i> Manage sizes</button></td></tr>
        @endforeach</tbody>
    </table></div></div>
</div>
<div class="modal fade" id="sizeModal"><div class="modal-dialog"><form method="POST" id="sizeForm" class="modal-content">@csrf @method('PUT')
    <div class="modal-header"><h5 class="modal-title">Manage sizes — <span id="sizeCustomer"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">Sizes *</label><textarea name="sizes" id="sizeValues" class="form-control" rows="5" required placeholder="XS, S, M, L, XL"></textarea><div class="form-text">Enter sizes separated by commas or new lines. Their order will be preserved.</div></div>
    <div class="modal-footer"><button class="btn btn-primary">Save size breakdown</button></div>
</form></div></div>
@push('scripts')<script>function editSizes(customer,sizes){document.getElementById('sizeForm').action='{{ url('admin/master-data/customer-sizes') }}/'+customer.id;document.getElementById('sizeCustomer').textContent=customer.name;document.getElementById('sizeValues').value=sizes.join(', ');new bootstrap.Modal(document.getElementById('sizeModal')).show();}</script>@endpush
@endsection
