@extends('layouts.app')
@section('title', 'BOM Size Mapping - ' . $order->CS)
@section('content')
<div class="container-fluid px-0">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><h5 class="mb-0">Complete BOM Size Mapping — {{ $order->CS }}</h5></div>
        <div class="card-body">
            <p class="mb-1"><strong>Style:</strong> {{ $order->SNo }} — {{ $order->Sname }}</p>
            <p class="mb-0 text-muted">Items marked “All sizes” are selected automatically. For “Map on order”, choose every product size that uses that material.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.ocs.bom-size-mapping.save', $order->id) }}">@csrf @method('PUT')
        <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-bordered align-middle mb-0">
            <thead class="table-light"><tr><th>Material</th><th>Description</th>@foreach($sizes as $size)<th class="text-center">{{ $size->size_name }}<br><small>Qty {{ $size->quantity }}</small></th>@endforeach</tr></thead>
            <tbody>@foreach($items as $item)<tr>
                <td><code>{{ $item->material_code }}</code></td><td>{{ $item->material_name }}</td>
                @foreach($sizes as $size) @php($checked = ($mapped[$item->id] ?? collect())->contains('order_size_id', $size->id))
                <td class="text-center"><input class="form-check-input" type="checkbox" name="mappings[{{ $item->id }}][]" value="{{ $size->id }}" @checked($checked) @disabled(($item->size_rule ?? 'all') === 'all')>
                @if(($item->size_rule ?? 'all') === 'all')<input type="hidden" name="mappings[{{ $item->id }}][]" value="{{ $size->id }}">@endif</td>
                @endforeach
            </tr>@endforeach</tbody>
        </table></div></div>
        <div class="d-flex gap-2 mt-3"><a href="{{ route('admin.ocs.index') }}" class="btn btn-secondary">Save later</a><button class="btn btn-primary">Confirm Mapping &amp; Mark BOM Ready</button></div>
    </form>
</div>
@endsection
