@extends('layouts.app')
@section('title', 'Edit warehouse issue')
@section('content')
<div class="container-fluid px-0">
    <a href="{{ route('admin.stock-records.show', $id) }}" class="btn btn-outline-secondary mb-3">Back to Stock Records</a>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h5 class="mb-0">Edit issue {{ $issue->issue_code }}</h5></div>
        <div class="card-body">
            <p><strong>{{ $material->internal_code }} — {{ $material->material_name }}</strong><br><span class="text-muted">{{ $transaction->material_color ?: '-' }} / {{ $transaction->material_size ?: '-' }} / {{ $transaction->lot_roll_no ?: '-' }}</span></p>
            @if($deliveryBill)<div class="alert alert-info">This issue belongs to Delivery Bill <strong>{{ $deliveryBill->number }}</strong>. Saving creates a revised bill file; the previous file and audit history are retained.</div>@endif
            <form method="POST" action="{{ route('admin.stock-records.issues.update', [$id, $transaction->id]) }}" class="row g-3">
                @csrf @method('PUT')
                <div class="col-md-4"><label class="form-label">Issue date</label><input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', $issue->issue_date) }}" required></div>
                <div class="col-md-4"><label class="form-label">Quantity ({{ $transaction->unit }})</label><input type="number" name="quantity" min="0.0001" max="99999999" step="0.0001" class="form-control" value="{{ old('quantity', abs((float) $transaction->quantity)) }}" required></div>
                <div class="col-12"><label class="form-label">Note</label><textarea name="notes" maxlength="2000" class="form-control" rows="3">{{ old('notes', $transaction->notes) }}</textarea></div>
                <div class="col-12"><label class="form-label">Reason for change</label><textarea name="reason" maxlength="1000" class="form-control" rows="2" required>{{ old('reason') }}</textarea></div>
                <div class="col-12"><button class="btn btn-primary">Save changes</button><a href="{{ route('admin.stock-records.show', $id) }}" class="btn btn-outline-secondary">Cancel</a></div>
            </form>
            <p class="small text-muted mt-3 mb-0">Changes are audited. Saving adjusts on-hand, reserved, issued and requisition quantities together.</p>
        </div>
    </div>
</div>
@endsection
