@extends('layouts.app')
@section('title', 'Add Revenue')

@section('content')

<h3>Add Revenue</h3>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.revenue.store') }}">
    @csrf

    <div class="mb-3">
        <label>CS</label>
        <select id="csSelect" name="CS" class="form-control">
            <option value="">-- Select CS --</option>
            @foreach($ocs as $item)
            <option value="{{ $item->CS }}" {{ old('CS') == $item->CS ? 'selected' : '' }}>{{ $item->CS }}</option>
            @endforeach
        </select>
        @error('CS')
        <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label>Plan Out</label>
        <input type="number" name="planout" class="form-control" value="{{ old('planout') }}" required>
    </div>

    <div class="mb-3">
        <label>Actual Out</label>
        <input type="number" name="actualout" class="form-control" value="{{ old('actualout') }}" required>
    </div>

    <div class="mb-3">
        <label>Sewing MP</label>
        <input type="number" name="sewingmp" class="form-control" value="{{ old('sewingmp') }}" required>
    </div>

    <div class="mb-3">
        <label>Work Hours</label>
        <input type="number" name="workhrs" class="form-control" value="{{ old('workhrs') }}" required>
    </div>

    <div class="mb-3">
        <label>CMP</label>
        <input type="number" id="cmpInput" class="form-control" readonly>
    </div>

    <button class="btn btn-primary">Save</button>

    <a href="{{ route('admin.revenue.index') }}" class="btn btn-secondary">Back</a>

</form>

<script>
document.getElementById('csSelect').addEventListener('change', function () {
    let cs = this.value;

    if (!cs) {
        document.getElementById('cmpInput').value = '';
        return;
    }

    fetch('/get-cmt/' + cs)
        .then(res => res.json())
        .then(data => {
            document.getElementById('cmpInput').value = data.CMT ?? '';
        })
        .catch(err => {
            console.log(err);
        });
});
</script>

@endsection