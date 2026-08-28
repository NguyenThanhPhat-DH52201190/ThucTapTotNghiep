@extends('layouts.app')
@section('title', 'Inventory Transactions')
@section('content')

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-arrow-left-right me-2"></i>Transaction Log</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="material_code" class="form-control" placeholder="Material code" value="{{ request('material_code') }}">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="IN" {{ request('type')=='IN'?'selected':'' }}>IN</option>
                        <option value="OUT" {{ request('type')=='OUT'?'selected':'' }}>OUT</option>
                        <option value="TRANSFER" {{ request('type')=='TRANSFER'?'selected':'' }}>Transfer</option>
                        <option value="ADJUSTMENT" {{ request('type')=='ADJUSTMENT'?'selected':'' }}>Adjustment</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="From">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="To">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Filter</button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th><th>Type</th><th>Material</th><th>Qty</th><th>Unit</th>
                        <th>From</th><th>To</th><th>Reference</th><th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                        <tr>
                            <td><small>{{ \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i') }}</small></td>
                            <td>
                                @php $tc = match($t->transaction_type) { 'IN'=>'success', 'OUT'=>'danger', 'TRANSFER'=>'info', default=>'warning' } @endphp
                                <span class="badge bg-{{ $tc }}">{{ $t->transaction_type }}</span>
                            </td>
                            <td><code>{{ $t->material_code }}</code></td>
                            <td class="fw-bold text-{{ $t->transaction_type=='IN'?'success':($t->transaction_type=='OUT'?'danger':'dark') }}">
                                {{ $t->transaction_type == 'IN' ? '+' : '' }}{{ number_format($t->quantity, 2) }}
                            </td>
                            <td>{{ $t->unit }}</td>
                            <td><small>{{ $t->from_warehouse ?? '-' }}</small></td>
                            <td><small>{{ $t->to_warehouse ?? '-' }}</small></td>
                            <td><small class="text-muted">{{ $t->reference_type }}#{{ $t->reference_id }}</small></td>
                            <td><small>{{ $t->notes ?? '' }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-3 text-muted">No transactions</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
