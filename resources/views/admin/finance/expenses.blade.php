@extends('layouts.app')
@section('title', 'Expenses')
@section('content')
@php $canManage = auth()->user()->role === 'admin'; @endphp

<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <!-- Expense Form -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Record Expense</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.finance.expenses.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="expense_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="labor">🧑‍🏭 Labor</option>
                                <option value="overhead">🏭 Overhead</option>
                                <option value="utility">⚡ Utility</option>
                                <option value="maintenance">🔧 Maintenance</option>
                                <option value="other">📦 Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (đ)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="">--</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                    </form>
                </div>
            </div>

            <!-- Summary by category -->
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 fw-bold small">Summary ({{ $month }})</h6>
                </div>
                <div class="card-body py-2">
                    @foreach($totalByCategory as $cat)
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ ucfirst($cat->category) }}</span>
                            <span class="fw-bold">{{ number_format($cat->total, 0) }} đ</span>
                        </div>
                    @endforeach
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total</span>
                        <span>{{ number_format($totalByCategory->sum('total'), 0) }} đ</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense List -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-list me-2"></i>Expense Records</h6>
                    <form method="GET" class="d-flex gap-2">
                        <input type="month" name="month" class="form-control form-control-sm" style="width:160px" value="{{ $month }}" onchange="this.form.submit()">
                        <select name="category" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach(['labor','overhead','utility','maintenance','other'] as $c)
                                <option value="{{ $c }}" {{ request('category') == $c ? 'selected' : '' }}>{{ ucfirst($c) }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th><th>Category</th><th>Description</th>
                                <th class="text-end">Amount</th><th>Payment</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenses as $e)
                                <tr>
                                    <td>{{ $e->expense_date }}</td>
                                    <td>
                                        @php $ec = match($e->category) { 'labor'=>'primary', 'overhead'=>'warning', 'utility'=>'info', 'maintenance'=>'secondary', default=>'dark' } @endphp
                                        <span class="badge bg-{{ $ec }}">{{ $e->category }}</span>
                                    </td>
                                    <td><small>{{ $e->description }}</small></td>
                                    <td class="text-end fw-bold text-danger">{{ number_format($e->amount, 0) }} đ</td>
                                    <td><small>{{ $e->payment_method ?? '-' }}</small></td>
                                    <td>
                                        @if($canManage)
                                            <form action="{{ route('admin.finance.expenses.delete', $e->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Delete this expense?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-3 text-muted">No expenses</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $expenses->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
