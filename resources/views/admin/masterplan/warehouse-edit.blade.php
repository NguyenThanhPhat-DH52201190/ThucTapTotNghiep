@extends('layouts.app')
@section('title', 'Warehouse Planning')
@section('content')
<div class="container-fluid px-0">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold">Warehouse Planning — {{ $plan->CU }}</h5></div>
        <form method="POST" action="{{ route('masterplan.warehouse.update', $plan->id) }}">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Norm Date</label><input type="date" name="Norm_date" class="form-control" value="{{ old('Norm_date', $plan->Norm_date ? \Carbon\Carbon::parse($plan->Norm_date)->format('Y-m-d') : '') }}"></div>
                    <div class="col-md-3"><label class="form-label">Fabric Issue Date</label><input type="date" name="fabric_issue_date" class="form-control" value="{{ old('fabric_issue_date', $plan->fabric_issue_date ? \Carbon\Carbon::parse($plan->fabric_issue_date)->format('Y-m-d') : '') }}"></div>
                    <div class="col-md-3"><label class="form-label">Trims Issue Date</label><input type="date" name="trims_issue_date" class="form-control" value="{{ old('trims_issue_date', $plan->trims_issue_date ? \Carbon\Carbon::parse($plan->trims_issue_date)->format('Y-m-d') : '') }}"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="mps_notes" class="form-control" rows="3">{{ old('mps_notes', $plan->mps_notes ?? '') }}</textarea></div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2"><a href="{{ route('masterplan.view') }}" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary">Save Planning</button></div>
        </form>
    </div>
</div>
@endsection
