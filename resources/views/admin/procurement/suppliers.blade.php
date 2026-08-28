@extends('layouts.app')
@section('title', 'Suppliers')
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Suppliers</h5>
            @if($canManage)
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Lead Time</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $s)
                        <tr>
                            <td class="fw-bold">{{ $s->code }}</td>
                            <td>{{ $s->name }}</td>
                            <td><small>{{ $s->contact_person ?? '-' }}</small></td>
                            <td>{{ $s->phone ?? '-' }}</td>
                            <td><small>{{ $s->email ?? '-' }}</small></td>
                            <td>{{ $s->lead_time_days }}d</td>
                            <td><span class="badge bg-{{ $s->status=='active'?'success':'secondary' }}">{{ $s->status }}</span></td>
                            <td class="text-end">@if($canManage)<button type="button" class="btn btn-sm btn-warning" onclick='editSupplier(@json($s))'><i class="bi bi-pencil"></i></button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-3 text-muted">No suppliers</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.procurement.suppliers.store') }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Add Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Lead Time (days)</label>
                                <input type="number" name="lead_time_days" class="form-control" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Payment Terms</label>
                                <input type="text" name="payment_terms" class="form-control" placeholder="Net 30">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="editSupplierModal"><div class="modal-dialog"><form method="POST" id="editSupplierForm" class="modal-content">@csrf @method('PATCH')<div class="modal-header"><h5 class="modal-title">Edit supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-6"><label class="form-label">Code</label><input id="esCode" name="code" class="form-control" required></div><div class="col-6"><label class="form-label">Name</label><input id="esName" name="name" class="form-control" required></div><div class="col-6"><label class="form-label">Contact</label><input id="esContact" name="contact_person" class="form-control"></div><div class="col-6"><label class="form-label">Phone</label><input id="esPhone" name="phone" class="form-control"></div><div class="col-6"><label class="form-label">Email</label><input id="esEmail" name="email" type="email" class="form-control"></div><div class="col-6"><label class="form-label">Lead time</label><input id="esLead" name="lead_time_days" min="0" type="number" class="form-control"></div><div class="col-6"><label class="form-label">Payment terms</label><input id="esTerms" name="payment_terms" class="form-control"></div><div class="col-6"><label class="form-label">Status</label><select id="esStatus" name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="blacklisted">Blacklisted</option></select></div><div class="col-12"><label class="form-label">Address</label><textarea id="esAddress" name="address" class="form-control" rows="2"></textarea></div><div class="col-12"><label class="form-label">Notes</label><textarea id="esNotes" name="notes" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div>
@push('scripts')<script>function editSupplier(s){document.getElementById('editSupplierForm').action='{{ url('admin/procurement/suppliers') }}/'+s.id;for(const [id,key] of Object.entries({esCode:'code',esName:'name',esContact:'contact_person',esPhone:'phone',esEmail:'email',esLead:'lead_time_days',esTerms:'payment_terms',esStatus:'status',esAddress:'address',esNotes:'notes'})){document.getElementById(id).value=s[key]||'';}new bootstrap.Modal(document.getElementById('editSupplierModal')).show();}</script>@endpush
@endsection
