@extends('layouts.app')
@section('title', 'Bulk Edit Master Plan')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Bulk Edit Master Plan</h4>
        <div class="text-muted">Edit the selected CU rows, then save all changes together.</div>
    </div>
    <a href="{{ route('admin.masterplan.index') }}" class="btn btn-outline-secondary">Back</a>
</div>

@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@php
$fields = [
    'Require_date' => ['Required Date', 'date'],
    'Confirm_date' => ['Confirm Date', 'date'],
    'planned_cut_start' => ['Cut Start', 'date'],
    'planned_cut_end' => ['Cut End', 'date'],
    'planned_sew_start' => ['Sew Start', 'date'],
    'planned_sew_end' => ['Sew End', 'date'],
    'Norm_date' => ['Norm Date', 'date'],
    'inWHDate' => ['In WH Date', 'date'],
    '3rd_PartyInspection' => ['3rd Party Inspection', 'text'],
    'ShipDate2' => ['Ship Date 2', 'date'],
    'SoTK' => ['SoTK', 'text'],
    'ExQty' => ['ExQty', 'number'],
    'lt' => ['LT', 'number'],
    'FirstOPT' => ['FirstOPT', 'date'],
];
@endphp

<form method="POST" action="{{ route('admin.masterplan.bulk-update') }}">
    @csrf
    @method('PUT')
    <div class="d-flex justify-content-end mb-2">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Save changes to all selected Master Plan rows?')">
            <i class="bi bi-save me-1"></i>Save all ({{ count($plans) }})
        </button>
    </div>
    <div class="table-responsive" style="max-height: calc(100vh - 260px)">
        <table class="table table-bordered table-sm align-middle bg-white" style="min-width: 1900px">
            <thead class="table-light position-sticky top-0">
                <tr><th class="sticky-col bg-light">CU</th>@foreach($fields as [$label, $type])<th>{{ $label }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach($plans as $index => $plan)
                <tr>
                    <th class="sticky-col bg-white">
                        {{ $plan->CU }}
                        <input type="hidden" name="rows[{{ $index }}][id]" value="{{ $plan->id }}">
                    </th>
                    @foreach($fields as $field => [$label, $type])
                    @php $value = old("rows.{$index}.{$field}", $plan->{$field} ?? ''); @endphp
                    <td style="min-width: 150px">
                        <input class="form-control form-control-sm @error("rows.{$index}.{$field}") is-invalid @enderror"
                            type="{{ $type }}" name="rows[{{ $index }}][{{ $field }}]" value="{{ $value }}"
                            @if($type === 'number') min="0" step="1" @endif>
                        @error("rows.{$index}.{$field}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.masterplan.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Save changes to all selected Master Plan rows?')">Save all</button>
    </div>
</form>
<style>
    .sticky-col { position: sticky; left: 0; z-index: 2; min-width: 130px; }
    thead .sticky-col { z-index: 4; }
</style>
@endsection
