@extends('layouts.app')
@section('title', 'Order Cost Variance')
@section('content')
<div class="container-fluid px-0">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Order Cost Variance</h5>
            <form method="GET"><input name="cs" value="{{ request('cs') }}" placeholder="CS" class="form-control form-control-sm"></form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>CS</th><th>Style</th><th class="text-end">Est. material</th><th class="text-end">Actual material</th><th class="text-end">Est. labor</th><th class="text-end">Actual labor</th><th class="text-end">Actual FOB other</th><th class="text-end">Variance</th><th>Variance driver</th><th class="text-end">Revenue</th><th class="text-end">Profit</th></tr></thead>
                <tbody>
                    @forelse($costings as $costing)
                        @php($variances = ['Material' => $costing->material_variance, 'Labor' => $costing->labor_variance, 'FOB other' => $costing->other_variance])
                        @php($driver = collect($variances)->sortByDesc(fn ($variance) => abs($variance))->keys()->first())
                        @php($driverVariance = $variances[$driver])
                        <tr>
                            <td>{{ $costing->CS }}</td><td>{{ $costing->SNo }} &mdash; {{ $costing->Sname }}</td>
                            <td class="text-end">{{ number_format($costing->est_material_cost, 0) }}</td><td class="text-end">{{ number_format($costing->actual_material_cost, 0) }}</td>
                            <td class="text-end">{{ number_format($costing->est_labor_cost, 0) }}</td><td class="text-end">{{ number_format($costing->actual_labor_cost, 0) }}</td><td class="text-end">{{ number_format($costing->actual_other_cost, 0) }}</td>
                            <td class="text-end {{ $costing->total_variance > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($costing->total_variance, 0) }}</td>
                            <td>{{ $driver }} ({{ number_format($driverVariance, 0) }})</td>
                            <td class="text-end">{{ number_format($costing->revenue, 0) }}</td><td class="text-end fw-bold">{{ number_format($costing->final_profit, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-3 text-muted">No closed order costing snapshots.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $costings->links() }}</div>
    </div>
</div>
@endsection
