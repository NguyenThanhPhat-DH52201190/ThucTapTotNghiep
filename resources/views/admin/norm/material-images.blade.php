@extends('layouts.app')
@section('title', 'Material Images - '.$order->CS)
@section('content')
<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-3 py-3">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-images me-2"></i>Material Images</h5>
                <div class="text-muted">CS: <strong>{{ $order->CS }}</strong> · Style: <strong>{{ $order->SNo }}</strong> · {{ $materials->count() }} unique materials</div>
            </div>
            <a href="{{ route('admin.norm.materials.show', $order->id) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to NORM</a>
        </div>
    </div>

    <div class="row g-3">
        @forelse($materials as $material)
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <div class="card h-100 shadow-sm border-0 material-image-card">
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:260px;overflow:hidden">
                        @if($material->material_image_path && $material->image_material_id)
                            <img src="{{ route('admin.master-data.material-image', $material->image_material_id) }}" alt="{{ $material->material_name }}" class="w-100 h-100" style="object-fit:contain">
                        @else
                            <div class="text-center text-muted"><i class="bi bi-image fs-1 d-block mb-2"></i>No image available</div>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="fw-bold"><code>{{ $material->material_code }}</code></div>
                        <div class="text-muted">{{ $material->material_name }}</div>
                        <div class="small mt-2">{{ ucfirst($material->material_type ?: 'other') }} · {{ $material->material_color ?: '-' }} / {{ $material->material_size ?: '-' }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-info mb-0">No material requirements found for this NORM.</div></div>
        @endforelse
    </div>
</div>
@endsection
