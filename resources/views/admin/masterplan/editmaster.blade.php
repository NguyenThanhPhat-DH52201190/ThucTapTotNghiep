@extends('layouts.app')
@section('title', 'Edit Master Plan')
@section('content')
@php
    $fabricOnly = $fabricOnly ?? false;
    $updateRoute = $updateRoute ?? route('admin.masterplan.update', $plan->id);
    $readonly = $fabricOnly ? 'readonly' : '';
    $dateValue = static fn ($field) => old($field, $plan->$field ? \Carbon\Carbon::parse($plan->$field)->format('Y-m-d') : '');
@endphp

<div class="container-fluid px-0">
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $updateRoute }}">
        @csrf @method('PUT')

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Order &amp; Line Info</h5></div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-4"><label class="form-label">CU (CS)</label><input name="CU" class="form-control" value="{{ $plan->CU }}" readonly></div>
                <div class="col-md-3"><label class="form-label">PO</label><input class="form-control" value="{{ $plan->PO }}" readonly></div>
                <div class="col-md-3"><label class="form-label">Style</label><input class="form-control" value="{{ $plan->Style }}" readonly></div>
                <div class="col-md-2"><label class="form-label">OCS Qty</label><input class="form-control" value="{{ $plan->Qty ?? '-' }}" readonly></div>

                <div class="col-md-4">
                    <label class="form-label">Line <span class="text-danger">*</span></label>
                    @if($fabricOnly)
                        <input name="Line" class="form-control" value="{{ old('Line', $plan->Line) }}" readonly>
                    @else
                        <select name="Line" id="lineSelect" class="form-select" required>
                            <option value="">-- Select Line --</option>
                            @foreach(($colors ?? collect()) as $lineColor)
                                <option value="{{ $lineColor->name }}" data-hex="{{ $lineColor->hex_code }}" @selected(old('Line', $plan->Line) === $lineColor->name)>{{ $lineColor->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('Line')<div class="text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Line Color</label>
                    <div class="input-group">
                        <input type="hidden" name="LineColor" id="lineColorInput" value="{{ old('LineColor', $plan->LineColor ?? '#808080') }}" required>
                        <span class="input-group-text" style="min-width:58px;justify-content:center"><span id="lineColorSwatch" style="display:inline-block;width:28px;height:28px;border-radius:4px;border:1px solid #cbd5e1;background:#808080"></span></span>
                        <input type="text" class="form-control" id="lineColorText" value="{{ old('LineColor', $plan->LineColor ?? '#808080') }}" readonly>
                    </div>
                    @error('LineColor')<div class="text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2"><label class="form-label">LT (days)</label><input type="number" name="lt" class="form-control" min="0" value="{{ old('lt', $plan->lt) }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">Qty_dis</label><input type="number" name="Qty_dis" class="form-control" min="0" value="{{ old('Qty_dis', $plan->Qty_dis) }}" {{ $readonly }}>@error('Qty_dis')<div class="text-danger">{{ $message }}</div>@enderror</div>
            </div></div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>MPS Planning</h5></div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-3"><label class="form-label">MPS Status</label><select name="mps_status" class="form-select" {{ $fabricOnly ? 'disabled' : '' }}>
                    @foreach(['planned'=>'📋 Planned','in_production'=>'🏭 In Production','completed'=>'✅ Completed','on_hold'=>'⏸ On Hold'] as $value=>$label)<option value="{{ $value }}" @selected(old('mps_status', $plan->mps_status ?? 'planned') === $value)>{{ $label }}</option>@endforeach
                </select></div>
                <div class="col-md-3"><label class="form-label">Priority</label><select name="mps_priority" class="form-select" {{ $fabricOnly ? 'disabled' : '' }}>
                    @foreach(['low'=>'🟢 Low','medium'=>'🟡 Medium','high'=>'🟠 High','urgent'=>'🔴 Urgent'] as $value=>$label)<option value="{{ $value }}" @selected(old('mps_priority', $plan->mps_priority ?? 'medium') === $value)>{{ $label }}</option>@endforeach
                </select></div>
                <div class="col-md-3"><label class="form-label">Daily Target Qty</label><input type="number" name="daily_target_qty" class="form-control" min="0" value="{{ old('daily_target_qty', $plan->daily_target_qty) }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">Require Date</label><input type="date" class="form-control" value="{{ $plan->expected_ship_date ? \Carbon\Carbon::parse($plan->expected_ship_date)->format('Y-m-d') : '' }}" readonly><div class="form-text">Automatically taken from the selected OCS.</div></div>

                <div class="col-md-3"><label class="form-label">First OPT</label><input type="date" name="FirstOPT" class="form-control @error('FirstOPT') is-invalid @enderror" value="{{ old('FirstOPT', $dateValue('FirstOPT')) }}" {{ $readonly }}>@error('FirstOPT')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">Norm Date</label><input type="date" name="Norm_date" class="form-control" value="{{ $dateValue('Norm_date') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">Fabric Issue Date</label><input type="date" name="fabric_issue_date" class="form-control" value="{{ $dateValue('fabric_issue_date') }}" {{ $readonly }}>@error('fabric_issue_date')<div class="text-danger">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">Trims Issue Date</label><input type="date" name="trims_issue_date" class="form-control" value="{{ $dateValue('trims_issue_date') }}" {{ $readonly }}>@error('trims_issue_date')<div class="text-danger">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">In-Warehouse Date</label><input type="date" name="inWHDate" class="form-control" value="{{ $dateValue('inWHDate') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">3rd Party Inspection</label><input type="text" name="3rd_PartyInspection" class="form-control" value="{{ old('3rd_PartyInspection', $plan->{'3rd_PartyInspection'} ?? '') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">QA/QC Inspection Date</label><input type="date" name="qa_inspection_date" class="form-control" value="{{ $dateValue('qa_inspection_date') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">QA/QC Status</label><select name="qa_status" class="form-select" {{ $fabricOnly ? 'disabled' : '' }}>
                    <option value="not_approved" @selected(old('qa_status', $plan->qa_status ?? 'not_approved') === 'not_approved')>Not Approved</option>
                    <option value="approved" @selected(old('qa_status', $plan->qa_status ?? '') === 'approved')>Approved</option>
                </select></div>
                <div class="col-md-3"><label class="form-label">Confirm Date</label><input type="date" name="Confirm_date" class="form-control" value="{{ $dateValue('Confirm_date') }}" {{ $readonly }}>@error('Confirm_date')<div class="text-danger">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">Planned Cut Start</label><input type="date" name="planned_cut_start" class="form-control" value="{{ $dateValue('planned_cut_start') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">Planned Cut End</label><input type="date" name="planned_cut_end" class="form-control" value="{{ $dateValue('planned_cut_end') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">Planned Sew Start</label><input type="date" name="planned_sew_start" class="form-control" value="{{ $dateValue('planned_sew_start') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">Planned Sew End</label><input type="date" name="planned_sew_end" class="form-control" value="{{ $dateValue('planned_sew_end') }}" {{ $readonly }}></div>
            </div></div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Supply Chain</h5></div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-3"><label class="form-label">ExQty</label><input type="number" name="ExQty" class="form-control" min="0" value="{{ old('ExQty', $plan->ExQty) }}" {{ $readonly }}>@error('ExQty')<div class="text-danger">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">ShipDate2</label><input type="date" name="ShipDate2" class="form-control" value="{{ $dateValue('ShipDate2') }}" {{ $readonly }}></div>
                <div class="col-md-3"><label class="form-label">SoTK</label><input type="text" name="SoTK" class="form-control" value="{{ old('SoTK', $plan->SoTK ?? '') }}" {{ $readonly }}></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="mps_notes" class="form-control" rows="2" {{ $readonly }}>{{ old('mps_notes', $plan->mps_notes ?? '') }}</textarea></div>
            </div></div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <a href="{{ $fabricOnly ? route('masterplan.view') : route('admin.masterplan.index') }}" class="btn btn-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>{{ $fabricOnly ? 'Update Fabric-Trim' : 'Update Master Plan' }}</button>
        </div>
    </form>
</div>

<script>
    const lineSelect = document.getElementById('lineSelect');
    const lineColorInput = document.getElementById('lineColorInput');
    const lineColorText = document.getElementById('lineColorText');
    const lineColorSwatch = document.getElementById('lineColorSwatch');
    function applyLineColor(hex) {
        if (!hex) return;
        lineColorInput.value = hex;
        lineColorText.value = hex;
        lineColorSwatch.style.backgroundColor = hex;
    }
    if (lineSelect) lineSelect.addEventListener('change', () => applyLineColor(lineSelect.selectedOptions[0]?.dataset.hex));
</script>
@endsection
