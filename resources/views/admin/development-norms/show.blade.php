@extends('layouts.app')
@section('title', 'Development Norms - '.$norm->cs)
@section('content')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card shadow-sm border-0 mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h5 class="fw-bold mb-2">Development Norms - {{ $norm->cs }}</h5>
            <div class="d-flex flex-wrap gap-2"><span class="badge bg-light text-dark border">Style: {{ $norm->style_no }}{{ $norm->style_name ? ' - '.$norm->style_name : '' }}</span><span class="badge bg-light text-dark border">Customer: {{ $norm->customer ?: '-' }}</span><span class="badge bg-success-subtle text-success-emphasis">Product Qty: {{ number_format($norm->product_qty, 0) }}</span></div>
        </div>
        <small class="text-muted">Copied from BOM {{ $norm->bom_header_id }} · {{ \Carbon\Carbon::parse($norm->copied_at)->format('d/m/Y H:i') }}</small>
    </div></div>
    <div class="alert alert-info py-2">
        Enter one Development norm for each size. Weighted average = sum(size quantity × norm for that size) ÷ total CU quantity. Total adds the row's waste %. Changes stay in this Development copy and do not change the source BOM.
    </div>
    <form method="POST" action="{{ route('admin.development-norms.update', $norm->cutsheet_id) }}">
        @csrf @method('PUT')
        <div class="card shadow-sm border-0"><div class="table-responsive">
            <table class="table table-sm table-bordered table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Material Code</th><th>Old Code</th><th>Description</th><th>Type</th><th>Colour / BOM Size</th><th>Unit</th>
                    @foreach($sizes as $size)<th class="text-end">{{ $size->size_name }}<div class="small fw-normal text-muted">CU Qty {{ number_format($size->quantity, 0) }}</div></th>@endforeach
                    <th class="text-end">Weighted average</th><th class="text-end">Waste %</th><th class="text-end">Total incl. waste</th><th class="text-nowrap" style="min-width:150px">Remark</th>
                </tr></thead>
                <tbody>
                @forelse($items as $item)
                    @php
                        $waste = old('items.'.$item->id.'.waste_percent', $item->waste_percent);
                        $totalQty = (float) $sizes->sum('quantity');
                        $weighted = 0;
                        foreach ($sizes as $size) {
                            $weighted += (float) old('items.'.$item->id.'.size_rates.'.$size->id, $item->size_rates[$size->id]->yield_value ?? $item->yield_value) * (float) $size->quantity;
                        }
                        $average = $totalQty > 0 ? $weighted / $totalQty : 0;
                    @endphp
                    <tr data-norm-row>
                        <td><code>{{ $item->material_code }}</code></td><td><code>{{ $item->material_old_code ?: '-' }}</code></td><td>{{ $item->material_name }}</td>
                        <td><span class="badge bg-info">{{ ucfirst($item->material_type ?: 'other') }}</span></td><td>{{ $item->colour ?: '-' }} / {{ $item->size ?: '-' }}</td><td>{{ $item->unit ?: '-' }}</td>
                        @foreach($sizes as $size)
                            @php($sizeRate = old('items.'.$item->id.'.size_rates.'.$size->id, $item->size_rates[$size->id]->yield_value ?? $item->yield_value))
                            <td style="min-width:135px">
                                <input type="number" name="items[{{ $item->id }}][size_rates][{{ $size->id }}]" value="{{ $sizeRate }}" min="0" max="99999999" step="0.0001" class="form-control form-control-sm text-end" data-size-yield data-size-qty="{{ $size->quantity }}" required aria-label="{{ $item->material_code }} norm for size {{ $size->size_name }}">
                                <div class="small text-muted text-end mt-1">Need: <span data-size-need>{{ number_format((float) $sizeRate * (float) $size->quantity, 4) }}</span></div>
                            </td>
                        @endforeach
                        <td class="text-end fw-semibold" data-weighted-average>{{ number_format($average, 4) }}</td>
                        <td style="min-width:105px"><input type="number" name="items[{{ $item->id }}][waste_percent]" value="{{ $waste }}" min="0" max="100" step="0.01" class="form-control form-control-sm text-end" data-waste required aria-label="Development waste for {{ $item->material_code }}"></td>
                        <td class="text-end fw-bold" data-total>{{ number_format($average * (1 + (float) $waste / 100), 4) }}</td><td class="text-nowrap" style="min-width:150px">{{ $item->remark ?: '-' }}</td>
                    </tr>
                @empty<tr><td colspan="{{ 10 + $sizes->count() }}" class="text-center text-muted py-4">This OCS has no BOM material rows.</td>@endforelse
                </tbody>
            </table>
        </div><div class="card-footer d-flex justify-content-end"><button class="btn btn-primary" @disabled($items->isEmpty() || $sizes->isEmpty())><i class="bi bi-save me-1"></i>Save Development Norms</button></div></div>
    </form>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-norm-row]').forEach(row => {
    const rates = [...row.querySelectorAll('[data-size-yield]')];
    const waste = row.querySelector('[data-waste]');
    const averageCell = row.querySelector('[data-weighted-average]');
    const totalCell = row.querySelector('[data-total]');
    const totalQty = rates.reduce((sum, input) => sum + Number(input.dataset.sizeQty || 0), 0);
    const format = value => Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 4, maximumFractionDigits: 4});
    const recalculate = () => {
        let weightedNeed = 0;
        rates.forEach(input => {
            const need = Number(input.value || 0) * Number(input.dataset.sizeQty || 0);
            input.parentElement.querySelector('[data-size-need]').textContent = format(need);
            weightedNeed += need;
        });
        const average = totalQty > 0 ? weightedNeed / totalQty : 0;
        averageCell.textContent = format(average);
        totalCell.textContent = format(average * (1 + Number(waste.value || 0) / 100));
    };
    rates.forEach(input => input.addEventListener('input', recalculate));
    waste.addEventListener('input', recalculate);
    recalculate();
});
</script>
@endpush
