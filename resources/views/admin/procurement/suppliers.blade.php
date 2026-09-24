@extends('layouts.app')
@section('title', 'Suppliers')
@section('content')
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
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
        <form method="GET" action="{{ route('admin.procurement.suppliers') }}" class="row g-3 align-items-end px-3 pb-3">
            <div class="col-12 col-lg-5">
                <label for="supplierSearch" class="form-label">Search suppliers</label>
                <input type="search" id="supplierSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Code, name, email or contact">
            </div>
            <div class="col-md-4 col-lg-2">
                <label for="supplierCodeFilter" class="form-label">Code</label>
                <input id="supplierCodeFilter" name="code" value="{{ request('code') }}" class="form-control" placeholder="Enter supplier code">
            </div>
            <div class="col-md-4 col-lg-2">
                <label for="supplierCodeSort" class="form-label">Sort by Code</label>
                <select id="supplierCodeSort" name="sort" class="form-select">
                    <option value="code_asc" @selected(request('sort', 'code_asc') !== 'code_desc')>Ascending</option>
                    <option value="code_desc" @selected(request('sort') === 'code_desc')>Descending</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-dark"><i class="bi bi-search me-1"></i>Search</button>
                <a href="{{ route('admin.procurement.suppliers') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Name</th><th>Tax Code</th><th>Account Number</th><th>Bank Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Lead Time</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $s)
                        <tr>
                            <td class="fw-bold">{{ $s->code }}</td>
                            <td>{{ $s->name }}</td>
                            <td>{{ $s->tax_code ?? '-' }}</td>
                            @php $accounts = json_decode($s->bank_accounts ?? '[]', true) ?: []; @endphp
                            <td>@forelse($accounts as $account)<div class="text-nowrap">{{ $loop->iteration }}. {{ $account['account_number'] }}</div>@empty - @endforelse</td>
                            <td>@forelse($accounts as $account)<div class="text-nowrap">{{ $loop->iteration }}. {{ $account['bank_name'] }}</div>@empty - @endforelse</td>
                            <td><small>{{ $s->contact_person ?? '-' }}</small></td>
                            <td>{{ $s->phone ?? '-' }}</td>
                            <td><small>{{ $s->email ?? '-' }}</small></td>
                            <td>{{ $s->lead_time_days }}d</td>
                            <td><span class="badge bg-{{ $s->status=='active'?'success':'secondary' }}">{{ $s->status }}</span></td>
                            <td class="text-end">@if($canManage)<button type="button" class="btn btn-sm btn-warning" onclick='editSupplier(@json($s))'><i class="bi bi-pencil"></i></button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-3 text-muted">No suppliers</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($suppliers->hasPages())
            <div class="card-footer">{{ $suppliers->links() }}</div>
        @endif
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('admin.procurement.suppliers.store') }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Add Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-3"><div class="col-12"><label class="form-label">Tax Code</label><input name="tax_code" maxlength="50" class="form-control"></div>
<div class="col-12" data-bank-editor><label class="form-label">Bank accounts</label><div data-bank-rows></div><button type="button" class="btn btn-sm btn-outline-primary" data-add-bank>+ Add account</button></div>
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
<div class="modal fade" id="editSupplierModal"><div class="modal-dialog modal-lg"><form method="POST" id="editSupplierForm" class="modal-content">@csrf @method('PATCH')<input type="hidden" name="supplier_id" id="esId"><div class="modal-header"><h5 class="modal-title">Edit supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label">Tax Code</label><input name="tax_code" maxlength="50" class="form-control"></div>
<div class="col-12" data-bank-editor><label class="form-label">Bank accounts</label><div data-bank-rows></div><button type="button" class="btn btn-sm btn-outline-primary" data-add-bank>+ Add account</button></div><div class="col-6"><label class="form-label">Code</label><input id="esCode" name="code" class="form-control" required></div><div class="col-6"><label class="form-label">Name</label><input id="esName" name="name" class="form-control" required></div><div class="col-6"><label class="form-label">Contact</label><input id="esContact" name="contact_person" class="form-control"></div><div class="col-6"><label class="form-label">Phone</label><input id="esPhone" name="phone" class="form-control"></div><div class="col-6"><label class="form-label">Email</label><input id="esEmail" name="email" type="email" class="form-control"></div><div class="col-6"><label class="form-label">Lead time</label><input id="esLead" name="lead_time_days" min="0" type="number" class="form-control"></div><div class="col-6"><label class="form-label">Payment terms</label><input id="esTerms" name="payment_terms" class="form-control"></div><div class="col-6"><label class="form-label">Status</label><select id="esStatus" name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="blacklisted">Blacklisted</option></select></div><div class="col-12"><label class="form-label">Address</label><textarea id="esAddress" name="address" class="form-control" rows="2"></textarea></div><div class="col-12"><label class="form-label">Notes</label><textarea id="esNotes" name="notes" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div>
@push('scripts')<script>
function addBankRow(editor, account = {}) {
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2';
    row.innerHTML = '<div class="col-md-5"><input type="text" data-account class="form-control" maxlength="100" placeholder="Account number" aria-label="Account number"></div><div class="col-md-5"><input type="text" data-bank class="form-control" maxlength="191" placeholder="Bank name" aria-label="Bank name"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger">Remove</button></div>';
    row.querySelector('[data-account]').value = account.account_number || '';
    row.querySelector('[data-bank]').value = account.bank_name || '';
    row.querySelector('button').onclick = () => { row.remove(); reindexBanks(editor); };
    editor.querySelector('[data-bank-rows]').append(row);
    reindexBanks(editor);
}
function reindexBanks(editor) {
    const rows = editor.querySelector('[data-bank-rows]').children;
    Array.from(rows).forEach((row, i) => {
        row.querySelector('[data-account]').name = `bank_accounts[${i}][account_number]`;
        row.querySelector('[data-bank]').name = `bank_accounts[${i}][bank_name]`;
    });
    editor.querySelector('[data-add-bank]').disabled = rows.length >= 50;
}
function setBankRows(form, accounts) {
    const editor = form.querySelector('[data-bank-editor]');
    editor.querySelector('[data-bank-rows]').replaceChildren();
    accounts.forEach(account => addBankRow(editor, account));
    reindexBanks(editor);
}
document.querySelectorAll('[data-bank-editor]').forEach(editor => {
    editor.querySelector('[data-add-bank]').onclick = () => addBankRow(editor);
});
function editSupplier(s){document.getElementById('editSupplierForm').action='{{ url('admin/procurement/suppliers') }}/'+s.id;for(const [id,key] of Object.entries({esId:'id',esCode:'code',esName:'name',esContact:'contact_person',esPhone:'phone',esEmail:'email',esLead:'lead_time_days',esTerms:'payment_terms',esStatus:'status',esAddress:'address',esNotes:'notes'})){document.getElementById(id).value=s[key]||'';}const form = document.getElementById('editSupplierForm'); form.elements.tax_code.value = s.tax_code || ''; setBankRows(form, JSON.parse(s.bank_accounts || '[]')); new bootstrap.Modal(document.getElementById('editSupplierModal')).show();}
@if($errors->any())
document.addEventListener('DOMContentLoaded', () => {
    const previous = @json(old());
    const modal = document.getElementById(previous.supplier_id ? 'editSupplierModal' : 'addModal');
    const form = modal.querySelector('form');
    if (previous.supplier_id) form.action = @json(url('admin/procurement/suppliers')) + '/' + previous.supplier_id;
    for (const [key, value] of Object.entries(previous)) {
        if (['_token', '_method', 'bank_accounts'].includes(key)) continue;
        const field = form.elements.namedItem(key);
        if (field) field.value = value ?? '';
    }
    setBankRows(form, Object.values(previous.bank_accounts || []));
    new bootstrap.Modal(modal).show();
});
@endif
</script>@endpush
@endsection
