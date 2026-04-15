@extends('layouts.app')
@section('title', 'Edit Revenue')

@section('content')

<h3>Edit Revenue</h3>

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
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

<form method="POST" action="{{ route('admin.revenue.update', $revenue->id) }}">
    @csrf
    @method('PUT')

    <div class="row">

        <!-- CS (read-only) -->
        <div class="col-md-4 mb-3">
            <label>CS</label>
            <input type="text" class="form-control"
                value="{{ $revenue->CS }}" readonly>
        </div>

        <div class="col-md-4 mb-3">
            <label>SewingLine</label>
            <div class="line-badge js-line-color" data-line-color="{{ $revenue->LineColor ?? '#808080' }}">
                {{ $revenue->SewingLine }}
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <label>Distribution</label>
            <input type="number" class="form-control"
                value="{{ $revenue->Distribution }}" readonly>
        </div>

        <div class="col-md-4 mb-3">
            <label>Plan Out</label>
            <input type="number" name="planout" class="form-control"
                value="{{ old('planout', $revenue->planout) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>Actual Out</label>
            <input type="number" class="form-control"
                value="{{ $revenue->actualout }}" readonly>
            <small class="text-muted">Auto-updated from Daily Revenue by month.</small>
        </div>

        <div class="col-md-4 mb-3">
            <label>Sewing MP</label>
            <input type="number" name="sewingmp" class="form-control"
                value="{{ old('sewingmp', $revenue->sewingmp) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>Work Hours</label>
            <input type="number" name="workhrs" class="form-control"
                value="{{ old('workhrs', $revenue->workhrs) }}" required>
        </div>

        <div class="col-md-4 mb-3">
            <label>CMP</label>
            <input type="number" step="any" class="form-control"
                value="{{ $revenue->cmp }}" readonly>
        </div>

    </div>

    <button type="submit" class="btn btn-primary">Update</button>

    <a href="{{ route('admin.revenue.index') }}"
        class="btn btn-secondary">
        Back
    </a>

</form>

<style>
.line-badge {
    border-radius: 4px;
    text-align: center;
    color: #fff;
    font-weight: 500;
    padding: 8px 12px;
}
</style>

<script>
document.querySelectorAll('.js-line-color').forEach(function (el) {
    el.style.backgroundColor = el.dataset.lineColor || '#808080';
});
</script>

@endsection