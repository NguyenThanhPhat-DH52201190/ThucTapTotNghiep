@extends('layouts.app')
@section('title', 'Edit Master Plan')

@section('content')


<h3>Edit Master Plan</h3>
@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif
@error('Qty_dis')
<div class="text-danger">{{ $message }}</div>
@enderror
<form method="POST" action="{{ route('admin.masterplan.update', $plan->id) }}">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label>CU</label>
        <input type="text" name="CU" class="form-control"
            value="{{ $plan->CU }}" readonly>
    </div>

    <div class="mb-3">
        <label>Line</label>
        <input type="text" name="Line" class="form-control"
            value="{{ $plan->Line }}" required>
        @error('Line')
        <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label>Line Color</label>
        <div class="input-group">
            <input type="color" name="LineColor" class="form-control form-control-color" value="{{ $plan->LineColor ?? '#808080' }}" style="width: 60px;" required>
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
        <input type="date" name="Rdate" class="form-control"
            value="{{ old('Rdate', $plan->Rdate) }}"
            min="{{ date('Y-m-d') }}">
    </div>
    <div class="mb-3">
        <label>ETADate</label>
        <input type="date" name="ETADate" class="form-control"
            value="{{ old('ETADate', $plan->ETADate) }}"
            min="{{ date('Y-m-d') }}">
    </div>
    <div class="mb-3">
        <label>ActDate</label>
        <input type="date" name="ActDate" class="form-control"
            value="{{ old('ActDate', $plan->ActDate) }}"
            min="{{ date('Y-m-d') }}">
    </div>

    <div class="mb-3">
        <label>PO</label>
        <input type="text" class="form-control" value="{{ $plan->PO }}" readonly>
    </div>

    <div class="mb-3">
        <label>LT</label>
        <input type="number" name="lt" class="form-control" min="0" value="{{ old('lt', $plan->lt) }}">
    </div>

    <div class="mb-3">
        <label>FirstOPT</label>
        <input type="date" name="FirstOPT" class="form-control" value="{{ old('FirstOPT', $plan->FirstOPT ? \Carbon\Carbon::parse($plan->FirstOPT)->format('Y-m-d') : '') }}">
    </div>

    <div class="mb-3">
        <label>Finish_SEW</label>
        <input type="text" id="finishSew" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label>EX_Fact</label>
        <input type="text" id="exFact" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label>Qty_dis</label>
        <input type="number" name="Qty_dis" class="form-control" min="0" value="{{ $plan->Qty_dis }}">
    </div>

    <div class="mb-3">
        <label>Style</label>
        <input type="text" class="form-control" value="{{ $plan->Style }}" readonly>
    </div>

    <button class="btn btn-primary">Update</button>

    <a href="{{ route('admin.masterplan.index') }}" class="btn btn-secondary">
        Back
    </a>

</form>

@endsection