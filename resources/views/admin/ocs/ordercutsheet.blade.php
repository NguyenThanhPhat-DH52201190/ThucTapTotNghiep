@extends('layouts.app')
@section('title', 'Order Cutsheet')
@section('content')
@php
    $canManage = auth()->user()->role === 'admin';
@endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div class="d-flex flex-wrap gap-2 ocs-action-bar">
                    <a href="{{ route('ordercutsheet.export', request()->query()) }}" class="btn btn-success ocs-action-btn">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                    </a>
                    @if($canManage)
                        <a href="{{ route('admin.ocs.create') }}" class="btn btn-primary ocs-action-btn">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </a>
                        <button type="button" class="btn btn-outline-success ocs-action-btn" id="toggleImportPanel" aria-expanded="{{ $errors->has('file') ? 'true' : 'false' }}" aria-controls="importPanel">
                            <i class="bi bi-upload me-1"></i> Import Excel
                        </button>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label">CS</label>
                    <input type="text" name="cs" class="form-control" placeholder="Fill CS" value="{{ request('cs') }}">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label">Customer</label>
                    <input type="text" name="customer" class="form-control" placeholder="Fill Customer" value="{{ request('customer') }}">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label">SName</label>
                    <input type="text" name="sname" class="form-control" placeholder="Fill SName" value="{{ request('sname') }}">
                </div>
                <div class="col-12 col-md-2 col-lg-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="released" {{ request('status') == 'released' ? 'selected' : '' }}>Released</option>
                        <option value="in_production" {{ request('status') == 'in_production' ? 'selected' : '' }}>In Production</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-lg-2">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Search</button>
                        <a href="{{ request()->url() }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                    </div>
                </div>
            </form>

            @if($canManage)
                <div id="importPanel" class="ocs-import-panel mt-4 {{ $errors->has('file') ? '' : 'd-none' }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h6 class="mb-1"><i class="bi bi-file-earmark-arrow-up me-1"></i> Import Order Cutsheet</h6>
                            <p class="text-muted small mb-0">Chọn file Excel (.xlsx hoặc .xls, tối đa 2 MB). Hệ thống sẽ kiểm tra dữ liệu trước khi lưu.</p>
                        </div>
                        <a href="{{ route('ordercutsheet.export') }}" class="small text-decoration-none">
                            <i class="bi bi-download me-1"></i>Tải file mẫu theo cấu trúc Export
                        </a>
                    </div>

                    <form action="{{ route('admin.ocs.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label for="ocsImportFile" id="ocsImportDropzone" class="ocs-import-dropzone mb-3">
                            <i class="bi bi-cloud-arrow-up fs-2 d-block mb-2"></i>
                            <span class="fw-semibold d-block">Kéo thả file Excel vào đây hoặc bấm để chọn file</span>
                            <span class="text-muted small d-block mt-1" id="ocsSelectedFile">Chưa chọn file nào</span>
                        </label>
                        <input id="ocsImportFile" type="file" name="file" accept=".xlsx,.xls" class="visually-hidden" required>
                        @error('file')
                            <div class="text-danger small mb-3">{{ $message }}</div>
                        @enderror
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="submit" id="submitOcsImport" class="btn btn-success ocs-action-btn" disabled>
                                <i class="bi bi-upload me-1"></i> Import file
                            </button>
                            <button type="button" class="btn btn-outline-secondary ocs-action-btn" id="cancelImportPanel">Cancel</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>CS</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>ONum</th>
                        <th>SNo</th>
                        <th>SName</th>
                        <th>Customer</th>
                        <th>CsDate</th>
                        <th>Color</th>
                        <th>Qty</th>
                        <th>BOM</th>
                        <th>Ship Date</th>
                        @if($canManage)
                            <th>Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->CS }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.ocs.status', $item->id) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <select name="status" class="form-select form-select-sm status-select" data-cs="{{ $item->CS }}"
                                            onchange="submitStatusChange(this)"
                                            {{ in_array($item->status, ['closed', 'cancelled']) ? 'disabled' : '' }}
                                            style="min-width:110px; font-size:0.75rem;">
                                        <option value="pending" {{ $item->status == 'pending' ? 'selected' : '' }}>⏳ Pending</option>
                                        <option value="confirmed" {{ $item->status == 'confirmed' ? 'selected' : '' }}>✅ Confirmed</option>
                                        <option value="released" {{ $item->status == 'released' ? 'selected' : '' }}>Released</option>
                                        <option value="in_production" {{ $item->status == 'in_production' ? 'selected' : '' }}>🏭 In Production</option>
                                        <option value="completed" {{ $item->status == 'completed' ? 'selected' : '' }}>✔ Completed</option>
                                        <option value="closed" {{ $item->status == 'closed' ? 'selected' : '' }}>Closed</option>
                                        <option value="cancelled" {{ $item->status == 'cancelled' ? 'selected' : '' }}>❌ Cancelled</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                @php
                                    $priorityColor = match($item->priority ?? 'medium') {
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'secondary',
                                        default => 'info'
                                    };
                                @endphp
                                <span class="badge bg-{{ $priorityColor }}">
                                    {{ ucfirst($item->priority ?? 'medium') }}
                                </span>
                            </td>
                            <td>{{ $item->ONum }}</td>
                            <td>{{ $item->SNo }}</td>
                            <td>{{ $item->Sname }}</td>
                            <td>{{ $item->Customer }}</td>
                            <td>{{ $item->CsDate }}</td>
                            <td>{{ $item->Color }}</td>
                            <td class="fw-bold">{{ number_format($item->Qty) }}</td>
                            <td>
                                @if($item->bom_header_id)
                                    <span class="badge bg-success" title="BOM: {{ $item->bom_style }} v{{ $item->bom_version }}">
                                        <i class="bi bi-file-text"></i> {{ $item->bom_style }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary">No BOM</span>
                                @endif
                            </td>
                            <td><small>{{ $item->expected_ship_date ?? '-' }}</small></td>
                            @if($canManage)
                                <td>
                                    @if(in_array($item->status, ['in_production', 'completed']))
                                        <span class="badge bg-secondary">Locked</span>
                                    @else
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.ocs.edit', $item->id) }}" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.ocs.destroy', $item->id) }}" onsubmit="return confirm('Delete this order?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 13 : 12 }}" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No orders found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .ocs-action-btn {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding-inline: 1rem;
    }

    .ocs-import-panel {
        padding: 1.25rem;
        border: 1px solid #d9e5df;
        border-radius: .75rem;
        background: #f8fbf9;
    }

    .ocs-import-dropzone {
        display: block;
        margin: 0;
        padding: 1.5rem;
        text-align: center;
        color: #355146;
        border: 2px dashed #82b49a;
        border-radius: .65rem;
        background: #fff;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
    }

    .ocs-import-dropzone:hover,
    .ocs-import-dropzone.is-dragover {
        border-color: #198754;
        background: #eaf7ef;
        transform: translateY(-1px);
    }
</style>

@push('scripts')
<script>
// Auto-submit status change with confirmation
document.querySelectorAll('.status-select').forEach(sel => {
    sel.addEventListener('change', function(e) {
        if (!confirm('Change status of CS "' + this.dataset.cs + '" to ' + this.value + '?')) {
            this.value = this.getAttribute('data-prev') || this.options[0].value;
            e.preventDefault();
        }
    });
    sel.addEventListener('focus', function() {
        this.setAttribute('data-prev', this.value);
    });
});

const importPanel = document.getElementById('importPanel');
const toggleImportPanel = document.getElementById('toggleImportPanel');
const cancelImportPanel = document.getElementById('cancelImportPanel');
const importFile = document.getElementById('ocsImportFile');
const importDropzone = document.getElementById('ocsImportDropzone');
const selectedImportFile = document.getElementById('ocsSelectedFile');
const submitOcsImport = document.getElementById('submitOcsImport');

function updateImportFile(file) {
    if (!file) return;
    selectedImportFile.textContent = file.name;
    submitOcsImport.disabled = false;
}

if (toggleImportPanel) {
    toggleImportPanel.addEventListener('click', () => {
        const isHidden = importPanel.classList.toggle('d-none');
        toggleImportPanel.setAttribute('aria-expanded', String(!isHidden));
        if (!isHidden) importPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    cancelImportPanel.addEventListener('click', () => {
        importPanel.classList.add('d-none');
        toggleImportPanel.setAttribute('aria-expanded', 'false');
    });
    importFile.addEventListener('change', () => updateImportFile(importFile.files[0]));
    ['dragenter', 'dragover'].forEach(eventName => importDropzone.addEventListener(eventName, event => {
        event.preventDefault();
        importDropzone.classList.add('is-dragover');
    }));
    ['dragleave', 'drop'].forEach(eventName => importDropzone.addEventListener(eventName, event => {
        event.preventDefault();
        importDropzone.classList.remove('is-dragover');
    }));
    importDropzone.addEventListener('drop', event => {
        const [file] = event.dataTransfer.files;
        if (!file) return;
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        importFile.files = dataTransfer.files;
        updateImportFile(file);
    });
}
</script>
@endpush

@endsection
@push('scripts')
<script>
function submitStatusChange(select) {
    const reason = window.prompt('Reason for changing order status:');
    if (reason === null) { window.location.reload(); return; }
    const input = document.createElement('input'); input.type = 'hidden'; input.name = 'change_reason'; input.value = reason;
    select.form.appendChild(input); select.form.submit();
}
</script>
@endpush
