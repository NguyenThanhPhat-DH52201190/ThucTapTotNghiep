@extends('layouts.app')
@section('title', 'Monthly Revenue Report')
@section('content')
@php
$canManage = auth()->user()->role === 'admin';
@endphp

<form method="GET" action="{{ route('revenue.monthly-report') }}" class="row g-3 mb-3 align-items-end">
    <div class="col-md-2">
        <label>Year</label>
        <input type="number" name="year" class="form-control" min="2000" max="2100" value="{{ $year }}">
    </div>

    <div class="col-md-6 d-flex gap-2 flex-wrap revenue-actions">
        <button type="submit" class="btn btn-dark revenue-action-btn">Apply</button>
        <a href="{{ request()->url() }}" class="btn btn-outline-secondary revenue-action-btn">Reset</a>
        <a href="{{ $canManage ? route('admin.revenue.index') : route('revenue.view') }}" class="btn btn-secondary revenue-action-btn">Back Revenue</a>
    </div>
</form>

<div class="report-card p-3 rounded mb-4">
    <canvas id="monthlyRevenueChart" height="120"></canvas>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle text-center">
        <thead>
            <tr>
                <th>Month</th>
                <th>GSVPlan</th>
                <th>GSVActual</th>
                <th>SubconPlan</th>
                <th>SubconActual</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tableRows as $row)
            <tr>
                <td>{{ $row['monthLabel'] }}</td>
                <td>{{ $row['gsvPlan'] > 0 ? '$' . number_format($row['gsvPlan'], 2) : '$-' }}</td>
                <td>{{ $row['gsvActual'] > 0 ? '$' . number_format($row['gsvActual'], 2) : '$-' }}</td>
                <td>{{ $row['subconPlan'] > 0 ? '$' . number_format($row['subconPlan'], 2) : '$-' }}</td>
                <td>{{ $row['subconActual'] > 0 ? '$' . number_format($row['subconActual'], 2) : '$-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">No data for selected year.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="fw-bold table-light">
                <td>Total</td>
                <td>{{ '$' . number_format($totals['gsvPlan'] ?? 0, 2) }}</td>
                <td>{{ '$' . number_format($totals['gsvActual'] ?? 0, 2) }}</td>
                <td>{{ '$' . number_format($totals['subconPlan'] ?? 0, 2) }}</td>
                <td>{{ '$' . number_format($totals['subconActual'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<style>
.report-card {
    background: radial-gradient(circle at center, #585858 0%, #2f3239 100%);
    border: 1px solid #484b54;
}

.revenue-action-btn {
    min-height: 40px;
    padding: 0.375rem 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
}

.revenue-actions {
    row-gap: 0.5rem;
}

</style>

<div
    id="monthly-report-data"
    data-labels='@json($monthLabels)'
    data-datasets='@json($datasets)'
></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<script>
const reportDataEl = document.getElementById('monthly-report-data');
const labels = JSON.parse(reportDataEl.dataset.labels || '[]');
const datasets = JSON.parse(reportDataEl.dataset.datasets || '[]');

function moneyLabel(value) {
    if (!value || Number(value) === 0) {
        return '$-';
    }

    const decimalPart = Math.abs(Number(value) % 1);
    const fractionDigits = decimalPart === 0 ? 0 : 1;
    return '$' + Number(value).toLocaleString('en-US', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

const ctx = document.getElementById('monthlyRevenueChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels,
        datasets: datasets.map((item) => ({
            ...item,
            borderWidth: 0,
            borderRadius: 2,
            maxBarThickness: 22,
            categoryPercentage: 0.7,
            barPercentage: 0.9,
        })),
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    color: '#f1f5f9',
                    boxWidth: 14,
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + moneyLabel(context.raw);
                    }
                }
            },
            datalabels: {
                anchor: 'end',
                align: 'end',
                color: '#f8fafc',
                clamp: true,
                formatter: function(value) {
                    if (!value || Number(value) === 0) {
                        return '';
                    }

                    return moneyLabel(value);
                },
                font: {
                    size: 11,
                }
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(255,255,255,0.08)'
                },
                ticks: {
                    color: '#e2e8f0'
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(255,255,255,0.12)'
                },
                ticks: {
                    color: '#e2e8f0',
                    callback: function(value) {
                        return '$' + Number(value).toLocaleString('en-US');
                    }
                }
            }
        }
    },
    plugins: [ChartDataLabels],
});

</script>
@endsection
