@extends('layouts.app')
@section('title', 'Add Master Plan')

@section('content')
<div class="container">
    <h3 class="mb-4">Add Master Plan</h3>

    <form method="POST" action="{{ route('admin.masterplan.store') }}">
        @csrf

        <div class="mb-3">
            <label>CU</label>
            <select name="CU" id="cuSelect" class="form-control">
                <option value="">-- Chọn CU --</option>
                @foreach($ocs as $item)
                <option value="{{ $item->CS }}">
                    {{ $item->CS }}
                </option>
                @endforeach
            </select>
            @error('CU')
            <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label>Line</label>
            <input type="text" name="Line" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Rdate</label>
            <input type="date" name="Rdate" class="form-control" value="{{ old('Rdate') }}">
        </div>

        <div class="mb-3">
            <label>ETADate</label>
            <input type="date" name="ETADate" class="form-control" value="{{ old('ETADate') }}">
        </div>

        <div class="mb-3">
            <label>ActDate</label>
            <input type="date" name="ActDate" class="form-control" value="{{ old('ActDate') }}">
        </div>

        <div class="mb-3">
            <label>PO</label>
            <input type="text" id="poInput" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label>LT</label>
            <input type="number" name="lt" class="form-control" min="0" value="{{ old('lt') }}">
        </div>

        <div class="mb-3">
            <label>FirstOPT</label>
            <input type="date" name="FirstOPT" class="form-control" value="{{ old('FirstOPT') }}">
        </div>

        <div class="mb-3">
            <label>Qty_dis</label>
            <input type="number" name="Qty_dis" class="form-control" min="0">
        </div>

        <div class="mb-3">
            <label>Style</label>
            <input type="text" id="styleInput" class="form-control" readonly>
        </div>

        <div class="d-flex gap-2">
            <a href="{{route('admin.masterplan.index')}}" class="btn btn-primary">Back</a>
            <button type="submit" class="btn btn-success">
                Save
            </button>
        </div>

    </form>
</div>

<script>
    document.getElementById('cuSelect').addEventListener('change', function() {
        let cs = this.value;

        if (!cs) {
            document.getElementById('poInput').value = '';
            document.getElementById('styleInput').value = '';
            return;
        }

        fetch('/ocs-by-cs/' + cs)
            .then(res => res.json())
            .then(data => {
                document.getElementById('poInput').value = data?.ONum || '';
                document.getElementById('styleInput').value = data?.SNo || '';
            });
    });
</script>
@endsection