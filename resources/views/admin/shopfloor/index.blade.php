@extends('layouts.app')
@section('title', 'Production Orders')
@section('content')

@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Production Orders</h5>
                <a href="{{ route('admin.shopfloor.dashboard') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </div>

            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <input type="text" name="cu" class="form-control" placeholder="CU" value="{{ request('cu') }}">
                </div>
                <div class="col-md-2">
                    <select name="line" class="form-select">
                        <option value="">All Lines</option>
                        @foreach($lines as $l)
                            <option value="{{ $l->name }}" {{ request('line') == $l->name ? 'selected' : '' }}>{{ $l->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="operation" class="form-select">
                        <option value="">All Operations</option>
                        <option value="cut" {{ request('operation') == 'cut' ? 'selected' : '' }}>Cutting</option>
                        <option value="sewing" {{ request('operation') == 'sewing' ? 'selected' : '' }}>Sewing</option>
                        <option value="finishing" {{ request('operation') == 'finishing' ? 'selected' : '' }}>Finishing</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="not_started" {{ request('status') == 'not_started' ? 'selected' : '' }}>Not Started</option>
                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="on_hold" {{ request('status') == 'on_hold' ? 'selected' : '' }}>On Hold</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> Filter</button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>CU</th>
                        <th>Style</th>
                        <th>Line</th>
                        <th>Operation</th>
                        <th>Planned</th>
                        <th>Actual</th>
                        <th>Defects</th>
                        <th>WIP</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="fw-semibold">{{ $order->cu }}</td>
                            <td><small>{{ $order->Style }}</small></td>
                            <td>
                                <span class="badge" style="background:{{ $order->LineColor ?? '#808080' }}; color:#fff;">
                                    {{ $order->line }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $opIcon = match($order->operation) { 'cut' => '✂', 'sewing' => '🧵', 'finishing' => '✅', default => '📋' };
                                @endphp
                                {{ $opIcon }} {{ ucfirst($order->operation) }}
                            </td>
                            <td>{{ number_format($order->planned_qty) }}</td>
                            <td class="fw-bold">{{ number_format($order->actual_qty) }}</td>
                            <td class="text-danger">{{ $order->defect_qty }}</td>
                            <td>{{ $order->wip_qty }}</td>
                            <td>
                                @if($order->planned_qty > 0)
                                    @php $pct = min(100, round(($order->actual_qty / $order->planned_qty) * 100)); @endphp
                                    <div class="progress" style="height:6px;width:80px;">
                                        <div class="progress-bar bg-{{ $pct >= 100 ? 'success' : ($pct >= 50 ? 'info' : 'warning') }}"
                                             style="width:{{ $pct }}%"></div>
                                    </div>
                                    <small>{{ $pct }}%</small>
                                @endif
                            </td>
                            <td>
                                @php
                                    $sc = match($order->status) { 'in_progress'=>'info', 'completed'=>'success', 'on_hold'=>'danger', default=>'secondary' };
                                @endphp
                                <span class="badge bg-{{ $sc }}">{{ str_replace('_',' ',ucfirst($order->status)) }}</span>
                            </td>
                            <td><small>{{ $order->created_at ? \Carbon\Carbon::parse($order->created_at)->format('d/m') : '' }}</small></td>
                            <td>
                                <a href="{{ route('admin.shopfloor.show', $order->id) }}" class="btn btn-sm btn-info" title="Daily Entry">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center py-4 text-muted">No orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $orders->links() }}</div>
    </div>
</div>

@endsection
