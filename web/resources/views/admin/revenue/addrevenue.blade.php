@extends('layouts.app')
@section('title', 'Add Revenue')

@section('content')

<h3>Add Revenue</h3>

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

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
        <select id="csSelect" name="CS" class="form-control" required>
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
        <label>SewingLine</label>
        <select id="lineSelect" name="SewingLine" class="form-control" required>
            <option value="">-- Select Line --</option>
        </select>
        @error('SewingLine')
        <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label>Distribution</label>
        <input type="number" id="distributionInput" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label>Plan Out</label>
        <input type="number" name="planout" class="form-control" value="{{ old('planout') }}" required>
    </div>

    <div class="mb-3">
        <label>Actual Out</label>
        <input type="number" class="form-control" value="0" readonly>
        <small class="text-muted">Actual Out is auto-updated from Daily Revenue by month.</small>
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

<input type="hidden" id="oldLineValue" value="{{ old('SewingLine', '') }}">
<input type="hidden" id="oldCsValue" value="{{ old('CS', '') }}">
<input type="hidden" id="sewingLinesUrlTemplate" value="{{ route('revenue.sewing-lines', ['cs' => '__CS__']) }}">
<input type="hidden" id="distributionUrl" value="{{ route('revenue.distribution') }}">

<script>
const csSelect = document.getElementById('csSelect');
const lineSelect = document.getElementById('lineSelect');
const cmpInput = document.getElementById('cmpInput');
const distributionInput = document.getElementById('distributionInput');

const oldLine = document.getElementById('oldLineValue').value;
const oldCs = document.getElementById('oldCsValue').value;
const sewingLinesUrlTemplate = document.getElementById('sewingLinesUrlTemplate').value;
const distributionUrl = document.getElementById('distributionUrl').value;

function resetLineSelect() {
    lineSelect.innerHTML = '<option value="">-- Select Line --</option>';
    lineSelect.disabled = true;
}

function clearDerivedFields() {
    distributionInput.value = '';
    cmpInput.value = '';
}

async function loadCmp(cs) {
    if (!cs) {
        cmpInput.value = '';
        return;
    }

    try {
        const response = await fetch('/get-cmt/' + encodeURIComponent(cs));
        const data = await response.json();
        cmpInput.value = data.CMT ?? '';
    } catch (error) {
        console.error(error);
        cmpInput.value = '';
    }
}

async function loadLines(cs, selectedLine = '') {
    if (!cs) {
        resetLineSelect();
        return;
    }

    try {
        const response = await fetch(sewingLinesUrlTemplate.replace('__CS__', encodeURIComponent(cs)));
        const lines = await response.json();

        resetLineSelect();

        lines.forEach((line) => {
            const option = document.createElement('option');
            option.value = line;
            option.textContent = line;
            if (selectedLine && selectedLine === line) {
                option.selected = true;
            }
            lineSelect.appendChild(option);
        });

        lineSelect.disabled = false;

        if (selectedLine) {
            await loadDistribution(cs, selectedLine);
        }
    } catch (error) {
        console.error(error);
        resetLineSelect();
    }
}

async function loadDistribution(cs, line) {
    if (!cs || !line) {
        distributionInput.value = '';
        return;
    }

    try {
        const url = new URL(distributionUrl, window.location.origin);
        url.searchParams.set('cs', cs);
        url.searchParams.set('line', line);

        const response = await fetch(url.toString());
        const data = await response.json();
        distributionInput.value = data.distribution ?? '';
    } catch (error) {
        console.error(error);
        distributionInput.value = '';
    }
}

csSelect.addEventListener('change', async function () {
    const cs = this.value;
    clearDerivedFields();
    await loadCmp(cs);
    await loadLines(cs);
});

lineSelect.addEventListener('change', async function () {
    await loadDistribution(csSelect.value, this.value);
});

resetLineSelect();

if (oldCs) {
    loadCmp(oldCs);
    loadLines(oldCs, oldLine || '');
}
</script>

@endsection