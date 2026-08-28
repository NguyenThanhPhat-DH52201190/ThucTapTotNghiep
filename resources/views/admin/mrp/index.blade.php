@extends('layouts.app')
@section('title', 'MRP')
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-calculator me-2"></i>Material Requirements Planning</h5>
            @if($canManage)
                <a href="{{ route('admin.mrp.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> New MRP Calculation
                </a>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>MRP Code</th>
                        <th>Period</th>
                        <th>Materials</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mrps as $m)
                        <tr>
                            <td class="fw-bold">{{ $m->mrp_code }}</td>
                            <td><small>{{ $m->period_from ?: 'N/A' }} → {{ $m->period_to ?: 'N/A' }}</small></td>
                            <td>{{ $m->total_materials }}</td>
                            <td class="fw-bold">{{ number_format($m->total_cost, 0) }} đ</td>
                            <td>
                                @php $sc = match($m->status) { 'calculated'=>'success', 'approved'=>'primary', default=>'secondary' } @endphp
                                <span class="badge bg-{{ $sc }}">{{ ucfirst($m->status) }}</span>
                            </td>
                            <td><small>{{ $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') : '' }}</small></td>
                            <td>
                                <a href="{{ route('admin.mrp.show', $m->id) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                                @if($canManage)
                                    <form action="{{ route('admin.mrp.destroy', $m->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">No MRP calculations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $mrps->links() }}</div>
    </div>
</div>
@endsection
