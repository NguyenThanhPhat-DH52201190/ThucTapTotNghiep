@extends('layouts.app')
@section('title', 'Bulk Edit Master Plan')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Bulk Edit Master Plan</h4>
        <div class="text-muted">Edit the selected CU rows, then save all changes together.</div>
    </div>
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
    'fabric_issue_date' => ['Fabric Issue Date', 'date'],
    'trims_issue_date' => ['Trims Issue Date', 'date'],
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
        <table class="table table-bordered table-sm align-middle bg-white bulk-edit-table">
            <thead class="table-light position-sticky top-0">
                <tr>
                    <th class="sticky-col sticky-cu bg-light">CU</th>
                    <th class="sticky-line bg-light">Line</th>
                    <th class="sticky-style bg-light">Style</th>
                    <th class="sticky-qty bg-light">Qty</th>
                    @foreach($fields as [$label, $type])<th>{{ $label }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($plans as $index => $plan)
                <tr>
                    <th class="sticky-col bg-white">
                        {{ $plan->CU }}
                        <input type="hidden" name="rows[{{ $index }}][id]" value="{{ $plan->id }}">
                    </th>
                    <td class="sticky-line line-color-cell" data-line-color="{{ preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', (string) $plan->LineColor) ? $plan->LineColor : '#808080' }}">{{ $plan->Line }}</td>
                    <td class="sticky-style">{{ $plan->Style ?? '-' }}</td>
                    <td class="sticky-qty">{{ $plan->Qty_dis ?? '-' }}</td>
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
</form>
<style>
    .bulk-edit-table { min-width: max-content; white-space: nowrap; }
    .bulk-edit-table th, .bulk-edit-table td { white-space: nowrap; vertical-align: middle; }
    .bulk-edit-table th:not(.sticky-col):not(.sticky-line):not(.sticky-style):not(.sticky-qty),
    .bulk-edit-table td:not(.sticky-col):not(.sticky-line):not(.sticky-style):not(.sticky-qty) { min-width: 150px; }
    .bulk-edit-table input { min-width: 145px; }
    .sticky-col, .sticky-line, .sticky-style, .sticky-qty { position: sticky; z-index: 2; }
    .sticky-col { left: 0; width: 130px; min-width: 130px; }
    .sticky-line { left: 130px; width: 110px; min-width: 110px; background: #fff; }
    .line-color-cell { border-radius: 4px; text-align: center; color: #fff; font-weight: 500; }
    .sticky-style { left: 240px; width: 130px; min-width: 130px; background: #fff; }
    .sticky-qty { left: 370px; width: 90px; min-width: 90px; background: #fff; box-shadow: 2px 0 0 rgba(0,0,0,.08); }
    thead .sticky-col, thead .sticky-line, thead .sticky-style, thead .sticky-qty { z-index: 4; background: #f8f9fa; }
</style>
<script>
    document.querySelectorAll('.line-color-cell').forEach(function (cell) {
        cell.style.backgroundColor = cell.dataset.lineColor || '#808080';
    });
</script>
@endsection
