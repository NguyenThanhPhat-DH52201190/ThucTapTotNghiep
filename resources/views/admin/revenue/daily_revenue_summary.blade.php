@extends('layouts.app')
@section('title', 'Daily Revenue Summary')
@section('content')

<form method="GET" action="{{ route('revenue.daily.summary') }}" class="row g-3 mb-4">
    <div class="col-md-3">
        <label>Month</label>
        <input type="month" name="month" class="form-control" value="{{ $month }}">
    </div>
    <div class="col-md-9 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-dark">Apply</button>
        <a href="{{ route('admin.revenue.index') }}" class="btn btn-secondary">Back</a>
    </div>
</form>

<!-- Summary Table -->
<div class="mb-5">
    <h5 class="text-uppercase fw-bold">Daily Plan Vs Actual Revenue - {{ strtoupper($monthLabel) }}</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-sm matrix-table align-middle">
            <thead>
                <tr>
                    <th class="sticky-col">Date</th>
                    @foreach($days as $day)
                        <th class="text-center">{{ str_pad($day, 2, '0', STR_PAD_LEFT) }}-{{ $monthLabel }}</th>
                    @endforeach
                    <th class="text-center total-col">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matrixLines as $line)
                    @php
                    $lineColor = $lineColors[$line] ?? '#6b7280';
                    @endphp
                    <tr>
                        <td class="sticky-col">
                            <span class="line-badge-item" style="background-color: {{ $lineColor }};">{{ strtoupper($line) }}</span>
                        </td>
                        @foreach($days as $day)
                            @php $amount = $dailyRevenueMatrix[$line][$day] ?? 0; @endphp
                            <td class="text-end amount-cell">
                                @if($amount > 0)
                                    $ {{ number_format($amount, 0) }}
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end fw-bold total-col">$ {{ number_format($lineTotals[$line] ?? 0, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold total-row">
                    <td class="sticky-col">Total</td>
                    @foreach($days as $day)
                        <td class="text-end">$ {{ number_format($dailyTotals[$day] ?? 0, 0) }}</td>
                    @endforeach
                    <td class="text-end total-col">$ {{ number_format($totalRevenue, 0) }}</td>
                </tr>
                <tr class="fw-bold target-row">
                    <td class="sticky-col">Target</td>
                    @foreach($days as $day)
                        <td class="text-end">$ {{ number_format($dailyTotalPlanout[$day] ?? 0, 0) }}</td>
                    @endforeach
                    <td class="text-end total-col">$ {{ number_format($targetTotal, 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Daily Revenue + Planout Chart -->
<div class="mb-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h6 class="card-title">Daily Revenue & Total Planout - {{ $monthLabel }}</h6>
            <div style="position: relative; height: 400px;">
                <canvas id="dailyRevenuePlanoutChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Daily Line Output Table -->
<div class="mb-5">
    <h5 class="text-uppercase fw-bold">Daily Line Output - {{ strtoupper($monthLabel) }}</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-sm matrix-table align-middle">
            <thead>
                <tr>
                    <th class="sticky-col">Date</th>
                    @foreach($days as $day)
                        <th class="text-center">{{ str_pad($day, 2, '0', STR_PAD_LEFT) }}-{{ $monthLabel }}</th>
                    @endforeach
                    <th class="text-center total-col">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outputLines as $line)
                    <tr>
                        <td class="sticky-col fw-bold">{{ strtoupper($line) }}</td>
                        @foreach($days as $day)
                            @php $qty = $dailyOutputMatrix[$line][$day] ?? 0; @endphp
                            <td class="text-end amount-cell">
                                @if($qty > 0)
                                    {{ number_format($qty, 0) }}
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end fw-bold total-col">{{ number_format($outputLineTotals[$line] ?? 0, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold total-row">
                    <td class="sticky-col">Total</td>
                    @foreach($days as $day)
                        <td class="text-end">{{ number_format($dailyOutputTotals[$day] ?? 0, 0) }}</td>
                    @endforeach
                    <td class="text-end total-col">{{ number_format($outputGrandTotal, 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Daily Line Output Chart -->
<div class="mb-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h6 class="card-title">Daily Line Output - {{ strtoupper($monthLabel) }}</h6>
            <div style="position: relative; height: 400px;">
                <canvas id="dailyLineOutputChart"></canvas>
            </div>
        </div>
    </div>
</div>

<style>
    .table {
        font-size: 0.95rem;
    }

    .table thead th {
        font-weight: 700;
        color: #000;
        white-space: nowrap;
    }

    .matrix-table .sticky-col {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 1;
        min-width: 110px;
    }

    .matrix-table .total-col {
        background-color: #8fd4f0;
    }

    .matrix-table .total-row td {
        background-color: #eef6fa;
    }

    .matrix-table .target-row td {
        background-color: #46b9e6;
        color: #001b2a;
    }

    .matrix-table .amount-cell {
        min-width: 72px;
    }

    .badge {
        font-weight: 500;
        padding: 6px 12px;
        border-radius: 4px;
        display: inline-block;
    }

    .line-badge-item {
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 4px;
        display: inline-block;
        color: white;
        min-width: 76px;
    }

    .card {
        border: none;
        border-radius: 8px;
        background: #fff;
    }

    .card-title {
        margin-bottom: 15px;
        font-weight: 600;
        color: #1e293b;
    }
</style>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

@php
    $dailyPlanData = array_values($dailyPlanRevenue);
    $dailyActualData = array_values($dailyActualRevenue);
    $dailyTotalPlanoutData = array_values($dailyTotalPlanout ?? []);
    $dailyOutputTotalData = array_values($dailyOutputTotals ?? []);
@endphp

<script>
window.chartDataConfig = {
    days: @json($days),
    dailyPlanRevenue: @json($dailyPlanData),
    dailyActualRevenue: @json($dailyActualData),
    dailyTotalPlanout: @json($dailyTotalPlanoutData),
    dailyOutputTotals: @json($dailyOutputTotalData)
};
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get data from config
    const days = window.chartDataConfig.days;

    const dailyPlanoutCtx = document.getElementById('dailyRevenuePlanoutChart');
    if (dailyPlanoutCtx) {
        new Chart(dailyPlanoutCtx, {
            type: 'line',
            data: {
                labels: days,
                datasets: [
                    {
                        label: 'Daily Revenue',
                        data: window.chartDataConfig.dailyPlanRevenue,
                        borderColor: '#FFC300',
                        backgroundColor: 'rgba(255, 195, 0, 0.10)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: false,
                        pointRadius: 4,
                        pointBackgroundColor: '#FFC300'
                    },
                    {
                        label: 'Total Plan Revenue ',
                        data: window.chartDataConfig.dailyTotalPlanout,
                        borderColor: '#0EA5E9',
                        backgroundColor: 'rgba(14, 165, 233, 0.10)',
                        borderWidth: 2,
                        tension: 0.2,
                        fill: false,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        },
                        title: {
                            display: true,
                            text: 'Revenue ($)',
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    const dailyOutputCtx = document.getElementById('dailyLineOutputChart');
    if (dailyOutputCtx) {
        new Chart(dailyOutputCtx, {
            type: 'line',
            data: {
                labels: days,
                datasets: [
                    {
                        label: 'Daily Line Output',
                        data: window.chartDataConfig.dailyOutputTotals,
                        borderColor: '#9333EA',
                        backgroundColor: 'rgba(147, 51, 234, 0.10)',
                        borderWidth: 3,
                        tension: 0.25,
                        fill: false,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#9333EA'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return Number(value).toLocaleString();
                            }
                        },
                        title: {
                            display: true,
                            text: 'Output Qty',
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush

@endsection
