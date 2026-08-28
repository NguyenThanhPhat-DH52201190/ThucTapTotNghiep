@extends('layouts.app')
@section('title', 'BOM & Tech Pack')
@section('content')

@php $canManage = auth()->user()->role === 'admin'; @endphp

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
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-file-text me-2"></i>Bill of Materials (BOM) & Tech Pack
                </h5>
                <div class="d-flex flex-wrap gap-2">
                    @if($canManage)
                        <a href="{{ route('admin.bom.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Create BOM
                        </a>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bi bi-upload me-1"></i> Import Excel
                        </button>
                    @endif
                </div>
            </div>

            <!-- Search Form -->
            <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label">Style No</label>
                    <input type="text" name="style_no" class="form-control" placeholder="Fill Style No" value="{{ request('style_no') }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Customer</label>
                    <input type="text" name="customer" class="form-control" placeholder="Fill Customer" value="{{ request('customer') }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Search</button>
                        <a href="{{ request()->url() }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- BOM List -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Style No</th>
                        <th>Style Name</th>
                        <th>Customer</th>
                        <th>Version</th>
                        <th>Fabric Cost</th>
                        <th>Trim Cost</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Created</th>
                        @if($canManage)
                            <th>Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($boms as $bom)
                        <tr>
                            <td><strong>{{ $bom->style_no }}</strong></td>
                            <td>{{ $bom->style_name }}</td>
                            <td>{{ $bom->customer }}</td>
                            <td><span class="badge bg-secondary">{{ $bom->version }}</span></td>
                            <td>{{ number_format($bom->total_fabric_cost, 0) }}</td>
                            <td>{{ number_format($bom->total_trim_cost, 0) }}</td>
                            <td><strong>{{ number_format($bom->total_fabric_cost + $bom->total_trim_cost, 0) }}</strong></td>
                            <td>
                                @if($bom->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @elseif($bom->status === 'draft')
                                    <span class="badge bg-warning text-dark">Draft</span>
                                @else
                                    <span class="badge bg-secondary">Archived</span>
                                @endif
                            </td>
                            <td>{{ $bom->created_at ? \Carbon\Carbon::parse($bom->created_at)->format('d/m/Y') : '' }}</td>
                            @if($canManage)
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.bom.show', $bom->id) }}" class="btn btn-sm btn-info" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.bom.edit', $bom->id) }}" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ route('admin.bom.export', $bom->id) }}" class="btn btn-sm btn-success" title="Export">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <form action="{{ route('admin.bom.destroy', $bom->id) }}" method="POST"
                                              onsubmit="return confirm('Delete this BOM?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-file-text fs-3 d-block mb-2"></i>
                                No BOM records found. Create a new BOM or import from Excel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $boms->links() }}
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.bom.import-preview') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import BOM from Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Upload an Excel file with columns: <strong>Code, Description, Colour, Size, Width, Unit, Yield, Remark</strong></p>
                    <div class="mb-3">
                        <label class="form-label">Excel File</label>
                        <input type="file" name="file" class="form-control" required accept=".xlsx,.xls">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Style No</label>
                        <select name="style_no_import" class="form-select">
                            <option value="">-- Select or type manually --</option>
                            @foreach($styles as $s)
                                <option value="{{ $s->SNo }}">{{ $s->SNo }} - {{ $s->Sname }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Preview & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
