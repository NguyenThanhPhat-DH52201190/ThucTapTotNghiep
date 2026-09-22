@extends('layouts.app')
@section('title', 'Customer Styles')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <h4>Styles — {{ $customer->name }} {{ $customer->brand ? '· '.$customer->brand : '' }}</h4>
    <a href="{{ route('admin.master-data.customers') }}" class="btn btn-outline-secondary">Customer Master</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<div class="card mb-4"><div class="card-body">
    <h5>Add Style</h5>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.customer-styles.store', $customer->id) }}" class="row g-3">
        @csrf
        <div class="col-md-3"><label class="form-label">Style No *</label><input name="style_no" value="{{ old('style_no') }}" class="form-control" maxlength="191" required></div>
        <div class="col-md-4"><label class="form-label">Style Name *</label><input name="style_name" value="{{ old('style_name') }}" class="form-control" maxlength="191" required></div>
        <div class="col-md-5"><label class="form-label">Image</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control"><div class="form-text">JPG, PNG, WebP or GIF. Maximum 2 MB.</div></div>
        <div class="col-12"><button class="btn btn-primary">Add Style</button></div>
    </form>
</div></div>
<form method="GET" class="d-flex gap-2 mb-3"><input name="search" value="{{ request('search') }}" placeholder="Search Style No" class="form-control" style="max-width:320px"><button class="btn btn-dark">Search</button><a class="btn btn-outline-secondary" href="{{ route('admin.customer-styles.index', $customer->id) }}">Reset</a></form>
@forelse($styles as $style)
<div class="card mb-3"><div class="card-body">
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.customer-styles.update', [$customer->id, $style->id]) }}" class="row g-3 align-items-end">
        @csrf @method('PUT')
        <div class="col-md-2">@if($style->image_path)<a href="{{ route('customer-styles.image', $style->id) }}" target="_blank" rel="noopener"><img src="{{ route('customer-styles.image', $style->id) }}" alt="{{ $style->style_no }}" class="img-thumbnail" style="width:130px;height:130px;object-fit:contain"></a>@else<span class="text-muted">No image</span>@endif</div>
        <div class="col-md-2"><label class="form-label">Style No *</label><input name="style_no" value="{{ $style->style_no }}" class="form-control" required maxlength="191"></div>
        <div class="col-md-3"><label class="form-label">Style Name *</label><input name="style_name" value="{{ $style->style_name }}" class="form-control" required maxlength="191"></div>
        <div class="col-md-3"><label class="form-label">Replace image</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control"><div class="form-text">Leave empty to keep image. Maximum 2 MB.</div></div>
        <div class="col-md-2"><button class="btn btn-primary">Save</button></div>
    </form>
</div></div>
@empty<div class="text-muted">No styles for this customer yet.</div>@endforelse
{{ $styles->links() }}
@endsection
