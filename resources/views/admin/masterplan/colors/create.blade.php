@extends('layouts.app')
@section('title', 'Add Line Color')

@section('content')
<h3>Add Line Color</h3>

@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.colors.store') }}">
    @csrf

    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
    </div>

    <div class="mb-3">
        <label>Hex Color</label>
        <div class="input-group">
            <input type="color" id="hexPicker" class="form-control form-control-color" value="{{ old('hex_code', '#808080') }}" style="width:60px;">
            <input type="text" id="hexInput" name="hex_code" class="form-control" value="{{ old('hex_code', '#808080') }}" required>
        </div>
    </div>

    <div class="mb-3">
        <label>Category</label>
        <select name="cate" class="form-control" required>
            <option value="GSV" {{ old('cate', 'GSV') === 'GSV' ? 'selected' : '' }}>GSV</option>
            <option value="Subcon" {{ old('cate') === 'Subcon' ? 'selected' : '' }}>Subcon</option>
        </select>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
        <label class="form-check-label" for="isActive">Active</label>
    </div>

    <button class="btn btn-primary">Save</button>
    <a href="{{ route('admin.colors.index') }}" class="btn btn-secondary">Back</a>
</form>

<script>
    const hexPicker = document.getElementById('hexPicker');
    const hexInput = document.getElementById('hexInput');

    hexPicker.addEventListener('input', function () {
        hexInput.value = this.value.toUpperCase();
    });

    hexInput.addEventListener('input', function () {
        const value = this.value.trim();
        if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(value)) {
            hexPicker.value = value;
        }
    });
</script>
@endsection
