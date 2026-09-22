@extends('layouts.app')
@section('title', 'Copy Materials')
@section('content')
<a href="{{ route('admin.master-data.materials') }}" class="btn btn-outline-secondary mb-3">Back to Material Master</a>
<form method="POST" action="{{ route('admin.master-data.materials.copy.store') }}" id="materialCopyForm">
    @csrf
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h5 class="fw-bold mb-2">Copy materials</h5>
            <p class="text-muted mb-0">Enter a new internal code for each row, then change size, color or other details. Use Copy on a draft row to create more variants. Up to 100 rows per save.</p>
            <small class="text-muted">Old codes start empty. Supplier mappings and inventory quantities are not copied.</small>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span id="copyRowCount" class="fw-semibold" aria-live="polite"></span>
                <span id="copyRowMessage" class="text-danger" role="status"></span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="materialCopyTable">
                <thead class="table-light"><tr><th>Source</th><th>New internal code *</th><th>Old code</th><th>Name *</th><th>Category *</th><th>Subcategory</th><th>Color</th><th>Size</th><th>Unit *</th><th>Copy image</th><th>Actions</th></tr></thead>
                <tbody id="materialCopyRows"></tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center gap-2">
            <a href="{{ route('admin.master-data.materials') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" id="saveMaterialCopies" class="btn btn-primary">Save all materials</button>
        </div>
    </div>
</form>
@php($copyRows = old('rows', $rows))
@php($copyConfig = ['rows' => $copyRows, 'sources' => $sources, 'categories' => $categories, 'subcategories' => $subcategories])
<script type="application/json" id="materialCopyData">@json($copyConfig)</script>
<script src="{{ asset('js/material-copy.js') }}?v={{ filemtime(public_path('js/material-copy.js')) }}" defer></script>
@endsection
