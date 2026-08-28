@extends('layouts.app')
@section('title', 'Add Master Plan')
@section('content')

<div class="container-fluid px-0">
    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.masterplan.store') }}">
        @csrf

        <!-- Card 1: Basic Info -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Order & Line Info</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">CU (CS) <span class="text-danger">*</span></label>
                        <select name="CU" id="cuSelect" class="form-control" required>
                            <option value="">-- Select CU --</option>
                            @foreach($ocs as $item)
                            <option value="{{ $item->CS }}"
                                data-po="{{ $item->ONum }}"
                                data-style="{{ $item->SNo }}"
                                data-qty="{{ $item->Qty }}"
                                data-bom="{{ $item->bom_style ?? 'No BOM' }}"
                                {{ old('CU') === $item->CS ? 'selected' : '' }}>
                                {{ $item->CS }} @if($item->bom_style) [BOM: {{ $item->bom_style }}] @endif
                            </option>
                            @endforeach
                        </select>
                        @error('CU')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">PO</label>
                        <input type="text" id="poInput" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Style</label>
                        <input type="text" id="styleInput" class="form-control" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">OCS Qty</label>
                        <input type="text" id="ocsQtyDisplay" class="form-control" readonly value="-">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Line <span class="text-danger">*</span></label>
                        <select name="Line" id="lineSelect" class="form-control" required>
                            <option value="">-- Select Line --</option>
                            @foreach(($colors ?? collect()) as $lineColor)
                            <option value="{{ $lineColor->name }}"
                                data-hex="{{ $lineColor->hex_code }}"
                                {{ old('Line') === $lineColor->name ? 'selected' : '' }}>
                                {{ $lineColor->name }}
                            </option>
                            @endforeach
                        </select>
                        @error('Line')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Line Color</label>
                        <div class="input-group">
                            <input type="hidden" name="LineColor" id="lineColorInput" value="{{ old('LineColor', '#808080') }}" required>
                            <span class="input-group-text" style="min-width: 58px; justify-content: center;">
                                <span id="lineColorSwatch" style="display:inline-block; width:28px; height:28px; border-radius:4px; border:1px solid #cbd5e1; background:#808080;"></span>
                            </span>
                            <input type="text" class="form-control" id="lineColorText" value="{{ old('LineColor', '#808080') }}" readonly>
                        </div>
                        @error('LineColor')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">LT (days)</label>
                        <input type="number" name="lt" class="form-control" min="0" value="{{ old('lt') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Qty_dis</label>
                        <input type="number" name="Qty_dis" id="qtyDisInput" class="form-control" min="0" value="{{ old('Qty_dis') }}">
                        @error('Qty_dis')<div class="text-danger" style="font-size: 0.875rem;">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ExQty</label>
                        <input type="number" name="ExQty" class="form-control" min="0" value="{{ old('ExQty') }}">
                        @error('ExQty')<div class="text-danger" style="font-size: 0.875rem;">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">FirstOPT</label>
                        <input type="date" name="FirstOPT" class="form-control @error('FirstOPT') is-invalid @enderror" value="{{ old('FirstOPT') }}">
                        @error('FirstOPT')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: MPS Planning -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>MPS Planning</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">MPS Status</label>
                        <select name="mps_status" class="form-select">
                            <option value="planned" {{ old('mps_status') == 'planned' ? 'selected' : '' }}>📋 Planned</option>
                            <option value="in_production" {{ old('mps_status') == 'in_production' ? 'selected' : '' }}>🏭 In Production</option>
                            <option value="completed" {{ old('mps_status') == 'completed' ? 'selected' : '' }}>✅ Completed</option>
                            <option value="on_hold" {{ old('mps_status') == 'on_hold' ? 'selected' : '' }}>⏸ On Hold</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Priority</label>
                        <select name="mps_priority" class="form-select">
                            <option value="low" {{ old('mps_priority') == 'low' ? 'selected' : '' }}>🟢 Low</option>
                            <option value="medium" {{ old('mps_priority') == 'medium' ? 'selected' : '' }}>🟡 Medium</option>
                            <option value="high" {{ old('mps_priority') == 'high' ? 'selected' : '' }}>🟠 High</option>
                            <option value="urgent" {{ old('mps_priority') == 'urgent' ? 'selected' : '' }}>🔴 Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Daily Target Qty</label>
                        <input type="number" name="daily_target_qty" class="form-control" min="0" value="{{ old('daily_target_qty') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Require Date</label>
                        <input type="date" name="Require_date" class="form-control" value="{{ old('Require_date') }}">
                        @error('Require_date')<div class="text-danger" style="font-size: 0.875rem;">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Confirm Date</label>
                        <input type="date" name="Confirm_date" class="form-control" value="{{ old('Confirm_date') }}">
                        @error('Confirm_date')<div class="text-danger" style="font-size: 0.875rem;">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Planned Cut Start</label>
                        <input type="date" name="planned_cut_start" class="form-control" value="{{ old('planned_cut_start') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Planned Cut End</label>
                        <input type="date" name="planned_cut_end" class="form-control" value="{{ old('planned_cut_end') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Planned Sew Start</label>
                        <input type="date" name="planned_sew_start" class="form-control" value="{{ old('planned_sew_start') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Planned Sew End</label>
                        <input type="date" name="planned_sew_end" class="form-control" value="{{ old('planned_sew_end') }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Materials -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Materials & Supply Chain</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Fabric1</label>
                        <input type="text" name="Fabric1" class="form-control" value="{{ old('Fabric1') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">ETA1</label>
                        <input type="date" name="ETA1" class="form-control" value="{{ old('ETA1') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Actual</label>
                        <input type="date" name="Actual" class="form-control" value="{{ old('Actual') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fabric2</label>
                        <input type="text" name="Fabric2" class="form-control" value="{{ old('Fabric2') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">ETA2</label>
                        <input type="date" name="ETA2" class="form-control" value="{{ old('ETA2') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Linning</label>
                        <input type="text" name="Linning" class="form-control" value="{{ old('Linning') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">ETA3</label>
                        <input type="date" name="ETA3" class="form-control" value="{{ old('ETA3') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pocket</label>
                        <input type="text" name="Pocket" class="form-control" value="{{ old('Pocket') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">ETA4</label>
                        <input type="date" name="ETA4" class="form-control" value="{{ old('ETA4') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trim</label>
                        <input type="text" name="Trim" class="form-control" value="{{ old('Trim') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Norm_date</label>
                        <input type="date" name="Norm_date" class="form-control" value="{{ old('Norm_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">inWHDate</label>
                        <input type="date" name="inWHDate" class="form-control" value="{{ old('inWHDate') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">3rd Party Inspection</label>
                        <input type="text" name="3rd_PartyInspection" class="form-control" value="{{ old('3rd_PartyInspection') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ShipDate2</label>
                        <input type="date" name="ShipDate2" class="form-control" value="{{ old('ShipDate2') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">SoTK</label>
                        <input type="text" name="SoTK" class="form-control" value="{{ old('SoTK') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="mps_notes" class="form-control" rows="2">{{ old('mps_notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <a href="{{route('admin.masterplan.index')}}" class="btn btn-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save</button>
        </div>
    </form>
</div>

<script>
    // Color picker
    const colorInput = document.getElementById('lineColorInput');
    const colorText = document.getElementById('lineColorText');
    const colorSwatch = document.getElementById('lineColorSwatch');
    const lineSelect = document.getElementById('lineSelect');

    function applyColor(hex) {
        if (!hex) return;
        colorInput.value = hex;
        colorText.value = hex;
        colorSwatch.style.backgroundColor = hex;
    }

    function syncColorFromLine() {
        if (!lineSelect) return;
        const selected = lineSelect.options[lineSelect.selectedIndex];
        const hex = selected ? selected.getAttribute('data-hex') : null;
        if (!hex) return;
        applyColor(hex);
    }

    applyColor(colorInput.value);
    if (lineSelect) {
        lineSelect.addEventListener('change', syncColorFromLine);
        syncColorFromLine();
    }

    // CU selector: auto-fill PO, Style, Qty
    const cuSelect = document.getElementById('cuSelect');
    const poInput = document.getElementById('poInput');
    const styleInput = document.getElementById('styleInput');
    const ocsQtyDisplay = document.getElementById('ocsQtyDisplay');

    cuSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        poInput.value = selected ? selected.getAttribute('data-po') || '' : '';
        styleInput.value = selected ? selected.getAttribute('data-style') || '' : '';
        ocsQtyDisplay.value = selected ? selected.getAttribute('data-qty') || '-' : '-';
    });

    // Trigger on load if old value exists
    if (cuSelect.value) {
        cuSelect.dispatchEvent(new Event('change'));
    }
</script>

@endsection
