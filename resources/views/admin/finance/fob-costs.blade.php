@extends('layouts.app')
@section('title', 'FOB Cost Components')
@section('content')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><h5 class="mb-0">FOB cost component</h5></div>
        <div class="card-body">
            @if($orders->isEmpty())
                <div class="text-muted">No FOB order is available. Set the order type to FOB before recording these costs.</div>
            @else
                <form method="POST" action="{{ route('admin.finance.fob-costs.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-4"><label class="form-label">FOB order</label><select name="cutsheet_id" class="form-select" required><option value="">Select CS</option>@foreach($orders as $order)<option value="{{ $order->id }}" @selected(old('cutsheet_id') == $order->id)>{{ $order->CS }} | {{ $order->SNo }} | Qty {{ number_format($order->Qty) }} | {{ ucfirst($order->material_ownership) }} material</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label">Cost stage</label><select name="cost_stage" class="form-select" required><option value="estimated">Estimated</option><option value="actual">Actual</option></select></div>
                    <div class="col-md-2"><label class="form-label">Cost type</label><select name="cost_type" class="form-select" required><option value="freight">Freight</option><option value="qc">QC</option><option value="packing">Packing</option><option value="overhead">Overhead</option><option value="other">Other</option></select></div>
                    <div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" min="0" step="0.01" class="form-control" value="{{ old('amount', 0) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Note</label><input name="note" maxlength="255" class="form-control" value="{{ old('note') }}"></div>
                    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-save"></i> Save component</button></div>
                </form>
            @endif
        </div>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h5 class="mb-0">FOB component history</h5></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>CS</th><th>Style</th><th>Stage</th><th>Type</th><th class="text-end">Amount</th><th>Note</th><th></th></tr></thead><tbody>
            @forelse($components as $component)
                <tr><td>{{ $component->CS }}</td><td>{{ $component->SNo }}</td><td>{{ ucfirst($component->cost_stage) }}</td><td>{{ strtoupper($component->cost_type) }}</td><td class="text-end">{{ number_format($component->amount, 0) }}</td><td>{{ $component->note }}</td><td class="text-end"><form method="POST" action="{{ route('admin.finance.fob-costs.delete', $component->id) }}" onsubmit="return confirm('Delete this component?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
            @empty
                <tr><td colspan="7" class="text-center py-3 text-muted">No FOB cost components recorded.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="card-footer">{{ $components->links() }}</div>
    </div>
</div>
@endsection
