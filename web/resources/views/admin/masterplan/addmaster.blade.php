@extends('layouts.app')
@section('title', 'Add Master Plan')

@section('content')
<div class="container">
    <h3 class="mb-4">Add Master Plan</h3>

    @if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
    @endif

    <form method="POST" action="{{ route('admin.masterplan.store') }}">
        @csrf

        <div class="mb-3">
            <label>CU</label>
            <select name="CU" id="cuSelect" class="form-control">
                <option value="">-- Select CU --</option>
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
            <input type="text" name="Line" class="form-control" placeholder="e.g., Green, Blue, KingTex" required>
            @error('Line')
            <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label>Line Color</label>
            <div class="input-group">
                <input type="color" name="LineColor" class="form-control form-control-color" value="#808080" style="width: 60px;" required>
                <input type="text" class="form-control" id="lineColorText" placeholder="Hex color" readonly>
                <button type="button" class="btn btn-outline-secondary" id="copyColorBtn" title="Copy hex color">
                    <i class="bi bi-files"></i> Copy
                </button>
            </div>
            @error('LineColor')
            <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>
        <script>
            const colorInput = document.querySelector('input[name="LineColor"]');
            const colorText = document.getElementById('lineColorText');
            const copyBtn = document.getElementById('copyColorBtn');
            
            // Update hex text when color changes
            colorInput.addEventListener('change', function() {
                colorText.value = this.value;
            });
            colorInput.addEventListener('input', function() {
                colorText.value = this.value;
            });
            colorText.value = colorInput.value;
            
            // Copy hex color to clipboard
            copyBtn.addEventListener('click', function() {
                navigator.clipboard.writeText(colorText.value).then(() => {
                    const originalText = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="bi bi-check"></i> Copied!';
                    setTimeout(() => {
                        copyBtn.innerHTML = originalText;
                    }, 2000);
                });
            });
            
            // Allow pasting hex color
            document.addEventListener('paste', function(e) {
                if (document.activeElement === colorInput) return;
                const text = e.clipboardData.getData('text');
                if (text.match(/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/)) {
                    e.preventDefault();
                    colorInput.value = text;
                    colorText.value = text;
                    colorInput.dispatchEvent(new Event('change'));
                }
            });
        </script>

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