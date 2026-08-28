<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopFloorController extends Controller
{
    public function downtime(Request $request)
    {
        $lines = DB::table('sewing_lines')->where('is_active', true)->orderBy('line_code')->get();
        $logs = DB::table('downtime_logs')->join('sewing_lines', 'downtime_logs.line_id', '=', 'sewing_lines.id')
            ->select('downtime_logs.*', 'sewing_lines.line_code', 'sewing_lines.line_name')
            ->when($request->filled('line_id'), fn ($query) => $query->where('downtime_logs.line_id', $request->line_id))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('downtime_logs.log_date', $request->date))
            ->orderByDesc('downtime_logs.log_date')->orderByDesc('downtime_logs.id')->paginate(30)->withQueryString();
        return view('admin.shopfloor.downtime', compact('lines', 'logs'));
    }

    public function storeDowntime(Request $request)
    {
        $data = $request->validate(['line_id' => 'required|exists:sewing_lines,id', 'log_date' => 'required|date|before_or_equal:today', 'downtime_minutes' => 'required|integer|min:1|max:1440', 'reason' => 'required|string|max:2000']);
        if (!DB::table('sewing_lines')->where('id', $data['line_id'])->exists()) return back()->withInput()->with('error', 'Selected sewing line is unavailable.');
        DB::table('downtime_logs')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Downtime recorded.');
    }

    /** Record actual output, labor hours and labor cost against an MPS schedule. */
    public function mpsLogs(Request $request)
    {
        $schedules = DB::table('mps_schedules')
            ->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')
            ->join('sewing_lines', 'mps_schedules.line_id', '=', 'sewing_lines.id')
            ->join('ocs', 'work_orders.cutsheet_id', '=', 'ocs.id')
            ->whereIn('work_orders.status', ['released', 'in_production'])
            ->select('mps_schedules.id', 'mps_schedules.daily_target_qty', 'mps_schedules.planned_start_date', 'mps_schedules.planned_end_date',
                'work_orders.wo_number', 'work_orders.smv', 'ocs.CS', 'ocs.SNo', 'sewing_lines.line_name')
            ->orderByDesc('mps_schedules.planned_start_date')->get();

        $logs = DB::table('daily_production_logs')
            ->join('mps_schedules', 'daily_production_logs.mps_schedule_id', '=', 'mps_schedules.id')
            ->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')
            ->join('sewing_lines', 'mps_schedules.line_id', '=', 'sewing_lines.id')
            ->join('ocs', 'work_orders.cutsheet_id', '=', 'ocs.id')
            ->select('daily_production_logs.*', 'work_orders.wo_number', 'work_orders.smv', 'ocs.CS', 'sewing_lines.line_name')
            ->when($request->filled('schedule_id'), fn ($query) => $query->where('daily_production_logs.mps_schedule_id', $request->schedule_id))
            ->orderByDesc('daily_production_logs.log_date')->paginate(20)->withQueryString();
        $detailsByLog = DB::table('production_log_details')->whereIn('log_id', $logs->pluck('id'))
            ->orderBy('color')->orderBy('size_name')->get()->groupBy('log_id');

        return view('admin.shopfloor.mps-logs', compact('schedules', 'logs', 'detailsByLog'));
    }

    public function storeMpsLog(Request $request)
    {
        $data = $request->validate([
            'mps_schedule_id' => 'required|exists:mps_schedules,id', 'log_date' => 'required|date',
            'operation_stage' => 'required|in:cut,sewing,finishing', 'target_qty' => 'required|integer|min:0',
            'actual_qty' => 'required|integer|min:0', 'defect_qty' => 'nullable|integer|min:0',
            'actual_workers' => 'nullable|integer|min:0', 'working_hours' => 'nullable|numeric|min:0',
            'labor_rate' => 'nullable|numeric|min:0',
            'details' => 'nullable|array', 'details.*.color' => 'nullable|string|max:100',
            'details.*.size_name' => 'nullable|string|max:50', 'details.*.quantity' => 'required_with:details|integer|min:1',
        ]);
        if ($data['log_date'] > now()->toDateString()) return back()->withInput()->with('error', 'Production date cannot be in the future.');
        if (($data['defect_qty'] ?? 0) > $data['actual_qty']) return back()->with('error', 'Defect quantity cannot exceed actual quantity.');
        if (!empty($data['details']) && collect($data['details'])->sum('quantity') !== ($data['actual_qty'] - ($data['defect_qty'] ?? 0))) {
            return back()->withInput()->with('error', 'Color/size detail quantity must equal the good quantity (actual minus defects).');
        }
        $details = $data['details'] ?? [];
        unset($data['details']);

        try {
            $wip = DB::transaction(function () use ($data, $details) {
                $schedule = DB::table('mps_schedules')->where('id', $data['mps_schedule_id'])->lockForUpdate()->first();
                // Serialize all lines belonging to this work order so a parallel
                // line cannot consume the same WIP twice.
                DB::table('mps_schedules')->where('work_order_id', $schedule->work_order_id)->lockForUpdate()->get();
                $previous = ['sewing' => 'cut', 'finishing' => 'sewing'][$data['operation_stage']] ?? null;
                $existing = DB::table('daily_production_logs')->where('mps_schedule_id', $schedule->id)
                    ->where('log_date', $data['log_date'])->where('operation_stage', $data['operation_stage'])->first();
                if ($previous) {
                    $available = DB::table('daily_production_logs')->join('mps_schedules', 'daily_production_logs.mps_schedule_id', '=', 'mps_schedules.id')
                        ->where('mps_schedules.work_order_id', $schedule->work_order_id)
                        ->where('operation_stage', $previous)->sum(DB::raw('actual_qty - defect_qty'));
                    $previousValue = $existing ? ($existing->actual_qty - $existing->defect_qty) : 0;
                    $completed = DB::table('daily_production_logs')->join('mps_schedules', 'daily_production_logs.mps_schedule_id', '=', 'mps_schedules.id')
                        ->where('mps_schedules.work_order_id', $schedule->work_order_id)
                        ->where('operation_stage', $data['operation_stage'])->sum(DB::raw('actual_qty - defect_qty')) - $previousValue;
                    if ($completed + $data['actual_qty'] - ($data['defect_qty'] ?? 0) > $available) {
                        throw new \RuntimeException('Output cannot exceed the usable output of the preceding operation.');
                    }
                }
                $workers = $data['actual_workers'] ?? 0;
                $hours = $data['working_hours'] ?? 0;
                $rate = $data['labor_rate'] ?? 0;
                $payload = $data + ['defect_qty' => $data['defect_qty'] ?? 0, 'actual_workers' => $workers,
                    'working_hours' => $hours, 'labor_rate' => $rate, 'actual_labor_cost' => $workers * $hours * $rate, 'updated_at' => now()];
                if ($existing) {
                    $logId = $existing->id;
                    DB::table('daily_production_logs')->where('id', $logId)->update($payload);
                } else {
                    $logId = DB::table('daily_production_logs')->insertGetId($payload + ['created_at' => now()]);
                }
                if (!empty($details)) {
                    DB::table('production_log_details')->where('log_id', $logId)->delete();
                    foreach ($details as $detail) {
                        DB::table('production_log_details')->insert([
                            'log_id' => $logId, 'color' => $detail['color'] ?? null, 'size_name' => $detail['size_name'] ?? null,
                            'quantity' => $detail['quantity'], 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                $cut = DB::table('daily_production_logs')->join('mps_schedules', 'daily_production_logs.mps_schedule_id', '=', 'mps_schedules.id')
                    ->where('mps_schedules.work_order_id', $schedule->work_order_id)->where('operation_stage', 'cut')
                    ->sum(DB::raw('actual_qty - defect_qty'));
                $sewing = DB::table('daily_production_logs')->join('mps_schedules', 'daily_production_logs.mps_schedule_id', '=', 'mps_schedules.id')
                    ->where('mps_schedules.work_order_id', $schedule->work_order_id)->where('operation_stage', 'sewing')
                    ->sum(DB::raw('actual_qty - defect_qty'));
                return max(0, $cut - $sewing);
            });
            return back()->with('success', "Production log saved. Current sewing WIP: {$wip}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    /**
     * Dashboard - Tổng quan sản xuất
     */
    public function dashboard(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $line = $request->input('line');

        $lines = DB::table('colors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        // Lấy production orders đang hoạt động
        $activeOrders = DB::table('production_orders')
            ->leftJoin('mtp', 'production_orders.mtp_id', '=', 'mtp.id')
            ->leftJoin('ocs', 'production_orders.cu', '=', 'ocs.CS')
            ->whereIn('production_orders.status', ['not_started', 'in_progress'])
            ->when($line, fn($q) => $q->where('production_orders.line', $line))
            ->select(
                'production_orders.*',
                'mtp.Qty_dis',
                'mtp.LineColor',
                'mtp.mps_status',
                'ocs.SNo as Style',
                'ocs.Sname as StyleName',
                'ocs.ONum as PO'
            )
            ->orderBy('production_orders.line')
            ->orderBy('production_orders.start_date')
            ->get();

        // Daily production summary for today
        $dailySummary = DB::table('daily_production')
            ->join('production_orders', 'daily_production.production_order_id', '=', 'production_orders.id')
            ->where('daily_production.production_date', $date)
            ->when($line, fn($q) => $q->where('production_orders.line', $line))
            ->select(
                'daily_production.operation',
                DB::raw('SUM(daily_production.actual_qty) as total_qty'),
                DB::raw('SUM(daily_production.defect_qty) as total_defects'),
                DB::raw('SUM(daily_production.downtime_minutes) as total_downtime'),
                DB::raw('COUNT(DISTINCT daily_production.production_order_id) as order_count')
            )
            ->groupBy('daily_production.operation')
            ->get()
            ->keyBy('operation');

        $colorSizeSummary = DB::table('production_log_details')
            ->join('daily_production_logs', 'production_log_details.log_id', '=', 'daily_production_logs.id')
            ->join('mps_schedules', 'daily_production_logs.mps_schedule_id', '=', 'mps_schedules.id')
            ->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')
            ->join('ocs', 'work_orders.cutsheet_id', '=', 'ocs.id')
            ->leftJoin('order_sizes', function ($join) {
                $join->on('order_sizes.cutsheet_id', '=', 'ocs.id')->on('order_sizes.size_name', '=', 'production_log_details.size_name');
            })
            ->whereDate('daily_production_logs.log_date', $date)
            ->select('ocs.CS', 'ocs.SNo', 'ocs.Color as order_color', 'production_log_details.color', 'production_log_details.size_name',
                DB::raw('MAX(order_sizes.quantity) as ordered_qty'), DB::raw('SUM(production_log_details.quantity) as produced_qty'))
            ->groupBy('ocs.CS', 'ocs.SNo', 'ocs.Color', 'production_log_details.color', 'production_log_details.size_name')
            ->orderBy('ocs.CS')->orderBy('production_log_details.color')->orderBy('production_log_details.size_name')->get();

        return view('admin.shopfloor.dashboard', compact(
            'activeOrders', 'dailySummary', 'colorSizeSummary', 'date', 'line', 'lines'
        ));
    }

    /**
     * Tạo Production Order từ MTP
     */
    public function createFromMtp($mtpId)
    {
        $mtp = DB::table('mtp')->where('id', $mtpId)->first();
        if (!$mtp) abort(404);

        // Tạo 3 production orders cho 3 công đoạn
        $operations = [
            ['operation' => 'cut', 'label' => 'Cutting'],
            ['operation' => 'sewing', 'label' => 'Sewing'],
            ['operation' => 'finishing', 'label' => 'Finishing'],
        ];

        $created = [];
        foreach ($operations as $op) {
            $existing = DB::table('production_orders')
                ->where('mtp_id', $mtpId)
                ->where('operation', $op['operation'])
                ->first();

            if ($existing) {
                $created[] = $existing;
                continue;
            }

            $id = DB::table('production_orders')->insertGetId([
                'mtp_id' => $mtpId,
                'cu' => $mtp->CU,
                'line' => $mtp->Line,
                'operation' => $op['operation'],
                'planned_qty' => $mtp->Qty_dis ?? 0,
                'actual_qty' => 0,
                'wip_qty' => 0,
                'status' => 'not_started',
                'created_by' => request()->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created[] = DB::table('production_orders')->find($id);
        }

        return redirect()->route('admin.shopfloor.index')
            ->with('success', 'Created ' . count($created) . ' production orders for CU: ' . $mtp->CU);
    }

    /**
     * Danh sách Production Orders
     */
    public function index(Request $request)
    {
        $query = DB::table('production_orders')
            ->leftJoin('mtp', 'production_orders.mtp_id', '=', 'mtp.id')
            ->leftJoin('ocs', 'production_orders.cu', '=', 'ocs.CS')
            ->select(
                'production_orders.*',
                'mtp.Qty_dis',
                'mtp.LineColor',
                'mtp.mps_status',
                'mtp.daily_target_qty',
                'ocs.SNo as Style',
                'ocs.Sname as StyleName',
                'ocs.ONum as PO',
                'ocs.Customer'
            );

        // Filters
        if ($request->filled('cu')) {
            $query->where('production_orders.cu', 'like', '%' . $request->cu . '%');
        }
        if ($request->filled('line')) {
            $query->where('production_orders.line', $request->line);
        }
        if ($request->filled('operation')) {
            $query->where('production_orders.operation', $request->operation);
        }
        if ($request->filled('status')) {
            $query->where('production_orders.status', $request->status);
        }

        $orders = $query->orderBy('production_orders.updated_at', 'desc')
            ->paginate(20);

        $lines = DB::table('colors')->where('is_active', 1)->orderBy('name')->get();

        return view('admin.shopfloor.index', compact('orders', 'lines'));
    }

    /**
     * Chi tiết Production Order & nhập daily production
     */
    public function show($id)
    {
        $order = DB::table('production_orders')
            ->leftJoin('mtp', 'production_orders.mtp_id', '=', 'mtp.id')
            ->leftJoin('ocs', 'production_orders.cu', '=', 'ocs.CS')
            ->select(
                'production_orders.*',
                'mtp.Qty_dis',
                'mtp.LineColor',
                'mtp.mps_status',
                'mtp.daily_target_qty',
                'mtp.planned_cut_start',
                'mtp.planned_cut_end',
                'mtp.planned_sew_start',
                'mtp.planned_sew_end',
                'mtp.actual_cut_start',
                'mtp.actual_cut_end',
                'mtp.actual_sew_start',
                'mtp.actual_sew_end',
                'ocs.SNo as Style',
                'ocs.Sname as StyleName',
                'ocs.ONum as PO',
                'ocs.Customer'
            )
            ->where('production_orders.id', $id)
            ->first();

        if (!$order) abort(404);

        // Daily entries for this order
        $dailyEntries = DB::table('daily_production')
            ->where('production_order_id', $id)
            ->orderBy('production_date', 'desc')
            ->get();

        // Tính toán summary
        $totalActual = $dailyEntries->sum('actual_qty');
        $totalDefects = $dailyEntries->sum('defect_qty');
        $totalDowntime = $dailyEntries->sum('downtime_minutes');
        $totalOperators = $dailyEntries->sum('operator_count');

        return view('admin.shopfloor.show', compact(
            'order', 'dailyEntries', 'totalActual', 'totalDefects', 'totalDowntime', 'totalOperators'
        ));
    }

    /**
     * Store daily production entry
     */
    public function storeDaily(Request $request)
    {
        $request->validate([
            'production_order_id' => 'required|exists:production_orders,id',
            'production_date' => 'required|date',
            'actual_qty' => 'required|integer|min:0',
            'defect_qty' => 'nullable|integer|min:0',
            'downtime_minutes' => 'nullable|integer|min:0',
            'operator_count' => 'nullable|integer|min:0',
            'notes' => 'nullable',
        ]);

        $order = DB::table('production_orders')->find($request->production_order_id);
        if (!$order) abort(404);

        $defectQty = $request->defect_qty ?? 0;
        $downtime = $request->downtime_minutes ?? 0;
        $operators = $request->operator_count ?? 0;
        $targetQty = $request->input('target_qty', $order->planned_qty ?: 1);

        // Validate: defect_qty cannot exceed actual_qty
        if ($defectQty > $request->actual_qty) {
            return back()->with('error', 'Defect quantity cannot exceed actual quantity!');
        }

        // Validate: production date cannot be in the future
        if ($request->production_date > now()->format('Y-m-d')) {
            return back()->with('error', 'Production date cannot be in the future!');
        }

        // Validate: order is not completed or on_hold
        if (in_array($order->status, ['completed', 'on_hold'])) {
            return back()->with('error', 'Cannot add production to a ' . $order->status . ' order!');
        }

        // Calculate efficiency (guard against division by zero)
        $efficiency = 0;
        if ($request->actual_qty > 0 && $targetQty > 0) {
            $efficiency = round(($request->actual_qty / $targetQty) * 100, 2);
        }

        try {
            DB::beginTransaction();

            // Check if entry exists for this date (upsert)
            $existing = DB::table('daily_production')
                ->where('production_order_id', $request->production_order_id)
                ->where('production_date', $request->production_date)
                ->first();

            if ($existing) {
                DB::table('daily_production')
                    ->where('id', $existing->id)
                    ->update([
                        'target_qty' => $targetQty,
                        'actual_qty' => $request->actual_qty,
                        'defect_qty' => $defectQty,
                        'downtime_minutes' => $downtime,
                        'efficiency' => $efficiency,
                        'operator_count' => $operators,
                        'notes' => $request->notes,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('daily_production')->insert([
                    'production_order_id' => $request->production_order_id,
                    'production_date' => $request->production_date,
                    'operation' => $order->operation,
                    'target_qty' => $targetQty,
                    'actual_qty' => $request->actual_qty,
                    'defect_qty' => $defectQty,
                    'downtime_minutes' => $downtime,
                    'efficiency' => $efficiency,
                    'operator_count' => $operators,
                    'notes' => $request->notes,
                    'created_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Recalculate production order totals
            $totals = DB::table('daily_production')
                ->where('production_order_id', $request->production_order_id)
                ->select(
                    DB::raw('SUM(actual_qty) as total_actual'),
                    DB::raw('SUM(defect_qty) as total_defects')
                )
                ->first();

            $newStatus = $order->status;
            $wipQty = max(0, $totals->total_actual - $totals->total_defects);
            if ($wipQty >= $order->planned_qty && $order->planned_qty > 0) {
                $newStatus = 'completed';
            } elseif ($totals->total_actual > 0) {
                $newStatus = 'in_progress';
            }

            DB::table('production_orders')
                ->where('id', $request->production_order_id)
                ->update([
                    'actual_qty' => $totals->total_actual,
                    'defect_qty' => $totals->total_defects,
                    'wip_qty' => max(0, $wipQty),
                    'status' => $newStatus,
                    'start_date' => DB::raw('COALESCE(start_date, "' . $request->production_date . '")'),
                    'end_date' => $newStatus === 'completed' ? $request->production_date : DB::raw('end_date'),
                    'updated_at' => now(),
                ]);

            // 🔗 Update actual dates in MTP when operation is completed
            if ($newStatus === 'completed' && $order->mtp_id) {
                $today = now()->format('Y-m-d');
                $updateMtp = [];

                if ($order->operation === 'cut') {
                    $updateMtp['actual_cut_end'] = $today;
                    if (!DB::table('mtp')->where('id', $order->mtp_id)->value('actual_cut_start')) {
                        $updateMtp['actual_cut_start'] = $today;
                    }
                } elseif ($order->operation === 'sewing') {
                    $updateMtp['actual_sew_end'] = $today;
                    if (!DB::table('mtp')->where('id', $order->mtp_id)->value('actual_sew_start')) {
                        $updateMtp['actual_sew_start'] = $today;
                    }
                }

                if (!empty($updateMtp)) {
                    $updateMtp['updated_at'] = now();
                    DB::table('mtp')->where('id', $order->mtp_id)->update($updateMtp);
                }
            }

            DB::commit();

            return back()->with('success', 'Daily production saved! Order status: ' . $newStatus);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Shop Floor daily entry failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to save: ' . $e->getMessage());
        }
    }

    /**
     * Update production order status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:not_started,in_progress,completed,on_hold',
        ]);

        DB::table('production_orders')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status updated to ' . $request->status);
    }

    /**
     * WIP Report - Báo cáo sản xuất dở dang
     */
    public function wipReport(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $report = DB::table('production_orders')
            ->leftJoin('mtp', 'production_orders.mtp_id', '=', 'mtp.id')
            ->leftJoin('ocs', 'production_orders.cu', '=', 'ocs.CS')
            ->whereIn('production_orders.status', ['not_started', 'in_progress'])
            ->select(
                'production_orders.*',
                'ocs.SNo as Style',
                'ocs.Sname as StyleName',
                'ocs.Customer',
                'mtp.Qty_dis',
                'mtp.LineColor',
                'mtp.mps_status',
                'mtp.daily_target_qty'
            )
            ->orderBy('production_orders.line')
            ->orderBy('production_orders.operation')
            ->get()
            ->groupBy('line');

        return view('admin.shopfloor.wip', compact('report', 'date'));
    }

    /**
     * Production Efficiency Report
     */
    public function efficiencyReport(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $line = $request->input('line');

        $query = DB::table('daily_production')
            ->join('production_orders', 'daily_production.production_order_id', '=', 'production_orders.id')
            ->whereYear('daily_production.production_date', substr($month, 0, 4))
            ->whereMonth('daily_production.production_date', substr($month, 5, 2));

        if ($line) {
            $query->where('production_orders.line', $line);
        }

        $report = $query->select(
            'daily_production.production_date',
            'daily_production.operation',
            'production_orders.line',
            DB::raw('SUM(daily_production.actual_qty) as total_qty'),
            DB::raw('SUM(daily_production.defect_qty) as total_defects'),
            DB::raw('SUM(daily_production.downtime_minutes) as total_downtime'),
            DB::raw('AVG(daily_production.efficiency) as avg_efficiency'),
            DB::raw('SUM(daily_production.operator_count) as total_operators')
        )
        ->groupBy('daily_production.production_date', 'daily_production.operation', 'production_orders.line')
        ->orderBy('daily_production.production_date')
        ->get();

        $lines = DB::table('colors')->where('is_active', 1)->orderBy('name')->get();

        return view('admin.shopfloor.efficiency', compact('report', 'month', 'line', 'lines'));
    }
}
