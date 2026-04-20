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
            <label>PO</label>
            <input type="text" id="poInput" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label>Style</label>
            <input type="text" id="styleInput" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label>Qty_dis</label>
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <input type="number" name="Qty_dis" id="qtyDisInput" class="form-control" min="0" value="{{ old('Qty_dis') }}" style="min-width: 120px;">
                    @error('Qty_dis')
                    <div class="text-danger" style="font-size: 0.875rem;">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-auto">
                    <span style="white-space: nowrap; font-size: 0.95rem; color: #666;">
                        OCS Qty: <strong id="ocsQtyDisplay">-</strong>
                    </span>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label>Fabric1</label>
            <input type="text" name="Fabric1" class="form-control" value="{{ old('Fabric1') }}">
        </div>

        <div class="mb-3">
            <label>ETA1</label>
            <input type="date" name="ETA1" class="form-control" value="{{ old('ETA1') }}">
        </div>

        <div class="mb-3">
            <label>Actual</label>
            <input type="date" name="Actual" class="form-control" value="{{ old('Actual') }}">
        </div>

        <div class="mb-3">
            <label>Fabric2</label>
            <input type="text" name="Fabric2" class="form-control" value="{{ old('Fabric2') }}">
        </div>

        <div class="mb-3">
            <label>ETA2</label>
            <input type="date" name="ETA2" class="form-control" value="{{ old('ETA2') }}">
        </div>

        <div class="mb-3">
            <label>Linning</label>
            <input type="text" name="Linning" class="form-control" value="{{ old('Linning') }}">
        </div>

        <div class="mb-3">
            <label>ETA3</label>
            <input type="date" name="ETA3" class="form-control" value="{{ old('ETA3') }}">
        </div>

        <div class="mb-3">
            <label>Pocket</label>
            <input type="text" name="Pocket" class="form-control" value="{{ old('Pocket') }}">
        </div>

        <div class="mb-3">
            <label>ETA4</label>
            <input type="date" name="ETA4" class="form-control" value="{{ old('ETA4') }}">
        </div>

        <div class="mb-3">
            <label>Trim</label>
            <input type="text" name="Trim" class="form-control" value="{{ old('Trim') }}">
        </div>

        <div class="mb-3">
            <label>inWHDate</label>
            <input type="date" name="inWHDate" class="form-control" value="{{ old('inWHDate') }}">
        </div>

        <div class="mb-3">
            <label>3rd Party Inspection</label>
            <input type="text" name="3rd_PartyInspection" class="form-control" value="{{ old('3rd_PartyInspection') }}">
        </div>

        <div class="mb-3">
            <label>ShipDate2</label>
            <input type="date" name="ShipDate2" class="form-control" value="{{ old('ShipDate2') }}">
        </div>

        <div class="mb-3">
            <label>SoTK</label>
            <input type="text" name="SoTK" class="form-control" value="{{ old('SoTK') }}">
        </div>

        <div class="mb-3">
            <label>ExQty</label>
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <input type="number" name="ExQty" class="form-control" min="0" value="{{ old('ExQty') }}" style="min-width: 120px;">
                    @error('ExQty')
                    <div class="text-danger" style="font-size: 0.875rem;">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-auto">
                    <span style="white-space: nowrap; font-size: 0.95rem; color: #666;">
                        Qty_dis: <strong id="qtyDisDisplay">{{ old('Qty_dis') ?: '-' }}</strong>
                    </span>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label>LT</label>
            <input type="number" name="lt" class="form-control" min="0" value="{{ old('lt') }}">
        </div>

        <div class="mb-3">
            <label>FirstOPT</label>
            <input type="date" name="FirstOPT" class="form-control @error('FirstOPT') is-invalid @enderror" value="{{ old('FirstOPT') }}">
            @error('FirstOPT')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
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
            document.getElementById('ocsQtyDisplay').textContent = '-';
            return;
        }

        fetch('/ocs-by-cs/' + cs)
            .then(res => res.json())
            .then(data => {
                document.getElementById('poInput').value = data?.ONum || '';
                document.getElementById('styleInput').value = data?.SNo || '';
                document.getElementById('ocsQtyDisplay').textContent = data?.Qty || '-';
            });
    });

    const qtyDisInput = document.getElementById('qtyDisInput');
    const qtyDisDisplay = document.getElementById('qtyDisDisplay');

    function syncQtyDisDisplay() {
        qtyDisDisplay.textContent = qtyDisInput.value ? qtyDisInput.value : '-';
    }

    qtyDisInput.addEventListener('input', syncQtyDisDisplay);
    syncQtyDisDisplay();
</script>
@endsection