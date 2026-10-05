@extends('layouts.app')
@section('title', 'Edit QA/QC Inspection')
@section('content')
<div class="container-fluid px-0">
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('masterplan.qa-qc.update', $plan->id) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>QA/QC Inspection</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">CU</label><input class="form-control" value="{{ $plan->CU }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">Line</label><input class="form-control" value="{{ $plan->Line }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">Style</label><input class="form-control" value="{{ $plan->Style }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">PO</label><input class="form-control" value="{{ $plan->PO }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">Order Quantity</label><input class="form-control" value="{{ $plan->Order_Qty }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">Distributed Quantity</label><input class="form-control" value="{{ $plan->Qty_dis }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">Required Date</label><input class="form-control" value="{{ $plan->Require_date }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">Confirmed Date</label><input class="form-control" value="{{ $plan->Confirm_date }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">Warehouse Date</label><input class="form-control" value="{{ $plan->inWHDate }}" readonly></div>
                    <div class="col-md-3">
                        <label for="third_party_inspection" class="form-label">Third-Party Inspection</label>
                        <input type="text" id="third_party_inspection" name="3rd_PartyInspection" maxlength="50" class="form-control @error('3rd_PartyInspection') is-invalid @enderror" value="{{ old('3rd_PartyInspection', $plan->{'3rd_PartyInspection'} ?? '') }}">
                        @error('3rd_PartyInspection')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="qa_inspection_date" class="form-label">Inspection Date</label>
                        <input type="date" id="qa_inspection_date" name="qa_inspection_date" class="form-control @error('qa_inspection_date') is-invalid @enderror" value="{{ old('qa_inspection_date', $plan->qa_inspection_date) }}">
                        @error('qa_inspection_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="qa_status" class="form-label">Status</label>
                        <select id="qa_status" name="qa_status" class="form-select @error('qa_status') is-invalid @enderror" required>
                            <option value="not_approved" @selected(old('qa_status', $plan->qa_status ?? 'not_approved') === 'not_approved')>Not Approved</option>
                            <option value="approved" @selected(old('qa_status', $plan->qa_status ?? '') === 'approved')>Approved</option>
                        </select>
                        @error('qa_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <a href="{{ auth()->user()->role === 'admin' ? route('admin.masterplan.index') : route('masterplan.view') }}" class="btn btn-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save QA/QC</button>
        </div>
    </form>
</div>
@endsection
