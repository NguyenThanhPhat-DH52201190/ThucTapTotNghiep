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
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('ordercutsheet.export', request()->query()) }}" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                    </a>
                    @if($canManage)
                        <a href="{{ route('admin.ocs.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </a>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label">CS</label>
                    <input type="text" name="cs" class="form-control" placeholder="Fill CS" value="{{ request('cs') }}">
                </div>

                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label">Customer</label>
                    <input type="text" name="customer" class="form-control" placeholder="Fill Customer" value="{{ request('customer') }}">
                </div>

                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label">SName</label>
                    <input type="text" name="sname" class="form-control" placeholder="Fill SName" value="{{ request('sname') }}">
                </div>

                <div class="col-12 col-lg-3">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button type="submit" class="btn btn-dark">
                            Search
                        </button>
                        <a href="{{ request()->url() }}" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <form action="{{ route('ocs.import') }}" method="POST" enctype="multipart/form-data" class="mt-2 p-3 rounded-3 border bg-light">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload me-1"></i> Import Excel
                        </button>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label">Import Excel</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">CS</th>
                        <th scope="col">ONum</th>
                        <th scope="col">SNo</th>
                        <th scope="col">SName</th>
                        <th scope="col">Customer</th>
                        <th scope="col">CsDate</th>
                        <th scope="col">CMT</th>
                        <th scope="col">Color</th>
                        <th scope="col">Qty</th>
                        @if($canManage)
                            <th scope="col">Edit</th>
                            <th scope="col">Delete</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->CS }}</td>
                            <td>{{ $item->ONum }}</td>
                            <td>{{ $item->SNo }}</td>
                            <td>{{ $item->Sname }}</td>
                            <td>{{ $item->Customer }}</td>
                            <td>{{ $item->CsDate }}</td>
                            <td>{{ $item->CMT }}</td>
                            <td>{{ $item->Color }}</td>
                            <td>{{ $item->Qty }}</td>
                            @if($canManage)
                                <td>
                                    <a href="{{ route('admin.ocs.edit', $item->id) }}" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.ocs.destroy', $item->id) }}" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 11 : 9 }}" class="text-center py-4 text-muted">
                                No data found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
