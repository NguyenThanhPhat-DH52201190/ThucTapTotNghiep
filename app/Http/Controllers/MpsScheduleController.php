<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MpsScheduleController extends Controller
{
    public function index()
    {
        $orders = DB::table('ocs')->whereNotIn('status', ['closed', 'cancelled'])
            ->select('id', 'CS', 'SNo', 'Qty', 'status')->orderByDesc('id')->get();
        $lines = DB::table('sewing_lines')->orderBy('line_code')->get();
        $lineColors = DB::table('colors')->where('is_active', 1)->orderBy('name')->get();
        $workOrders = DB::table('work_orders')->join('ocs', 'work_orders.cutsheet_id', '=', 'ocs.id')
            ->select('work_orders.*', 'ocs.CS', 'ocs.SNo', 'ocs.Qty as order_qty')->orderByDesc('work_orders.id')->get();
        $schedules = DB::table('mps_schedules')->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')
            ->join('ocs', 'work_orders.cutsheet_id', '=', 'ocs.id')->join('sewing_lines', 'mps_schedules.line_id', '=', 'sewing_lines.id')
            ->select('mps_schedules.*', 'work_orders.wo_number', 'work_orders.planned_qty', 'work_orders.smv', 'ocs.CS', 'sewing_lines.line_code', 'sewing_lines.line_name')
            ->orderByDesc('mps_schedules.planned_start_date')->get();
        return view('admin.mps.index', compact('orders', 'lines', 'lineColors', 'workOrders', 'schedules'));
    }

    public function storeLine(Request $request)
    {
        $data = $request->validate([
            'line_code' => 'required|string|max:50|unique:sewing_lines,line_code',
            'line_name' => 'required|string|max:191',
            'default_workers' => 'required|integer|min:1',
            'working_hours_per_day' => 'required|numeric|gt:0|max:24',
            'labor_rate' => 'nullable|numeric|min:0',
        ]);
        DB::table('sewing_lines')->insert($data + ['labor_rate' => $data['labor_rate'] ?? 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Sewing line created.');
    }

    public function updateLine(Request $request, int $id)
    {
        $data = $request->validate(['line_code' => 'required|string|max:50|unique:sewing_lines,line_code,' . $id, 'line_name' => 'required|string|max:191', 'default_workers' => 'required|integer|min:1', 'working_hours_per_day' => 'required|numeric|gt:0|max:24', 'labor_rate' => 'nullable|numeric|min:0', 'is_active' => 'required|boolean']);
        DB::table('sewing_lines')->where('id', $id)->update($data + ['labor_rate' => $data['labor_rate'] ?? 0, 'updated_at' => now()]);
        return back()->with('success', 'Sewing line updated.');
    }

    public function destroyLine(int $id)
    {
        if (DB::table('mps_schedules')->where('line_id', $id)->exists()) return back()->with('error', 'This line has schedules and cannot be deleted. Set it inactive instead.');
        DB::table('sewing_lines')->where('id', $id)->delete();
        return back()->with('success', 'Sewing line deleted.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'line_id' => 'required|exists:sewing_lines,id',
            'planned_start_date' => 'required|date', 'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'daily_target_qty' => 'required|integer|min:1',
        ]);
        $workOrder = DB::table('work_orders')->find($data['work_order_id']);
        $line = DB::table('sewing_lines')->find($data['line_id']);
        if (!$line) throw ValidationException::withMessages(['line_id' => ['The selected sewing line is unavailable.']]);
        $days = max(1, \Carbon\Carbon::parse($data['planned_start_date'])->diffInDays(\Carbon\Carbon::parse($data['planned_end_date'] ?? $data['planned_start_date'])) + 1);
        $scheduledMinutes = DB::table('mps_schedules')->join('work_orders','mps_schedules.work_order_id','=','work_orders.id')
            ->where('mps_schedules.line_id',$data['line_id'])->whereDate('mps_schedules.planned_start_date','<=',$data['planned_end_date'] ?? $data['planned_start_date'])->whereDate('mps_schedules.planned_end_date','>=',$data['planned_start_date'])->sum(DB::raw('mps_schedules.daily_target_qty * work_orders.smv'));
        $requestedMinutes = $data['daily_target_qty'] * $days * $workOrder->smv;
        $capacityMinutes = $days * $line->default_workers * $line->working_hours_per_day * 60;
        if (($scheduledMinutes + $requestedMinutes) > $capacityMinutes) throw ValidationException::withMessages(['daily_target_qty' => ['Schedule exceeds available line capacity.']]);
        $lastEta = DB::table('po_items')->join('mrp_suggestions', 'po_items.mrp_suggestion_id', '=', 'mrp_suggestions.id')
            ->where('mrp_suggestions.cutsheet_id', $workOrder->cutsheet_id)->max('po_items.expected_date');
        if ($lastEta && $data['planned_start_date'] < $lastEta) {
            throw ValidationException::withMessages(['planned_start_date' => ['Planned Start Date cannot be before the last material ETA.']]);
        }
        $scheduledQty = DB::table('mps_schedules')->where('work_order_id', $workOrder->id)
            ->sum(DB::raw('daily_target_qty * (DATEDIFF(COALESCE(planned_end_date, planned_start_date), planned_start_date) + 1)'));
        if ($scheduledQty + ($data['daily_target_qty'] * $days) > $workOrder->planned_qty) {
            throw ValidationException::withMessages(['daily_target_qty' => ['Scheduled quantity exceeds the work order planned quantity.']]);
        }
        $id = DB::table('mps_schedules')->insertGetId($data + [
            'material_eta_status' => $lastEta ? 'ready' : 'unknown', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $request->expectsJson()
            ? response()->json(['id' => $id, 'message' => 'MPS schedule created.'], 201)
            : back()->with('success', 'MPS schedule created.');
    }

    public function destroy(int $id)
    {
        if (DB::table('daily_production_logs')->where('mps_schedule_id', $id)->exists()) return back()->with('error', 'A schedule with production logs cannot be deleted.');
        DB::table('mps_schedules')->where('id', $id)->delete();
        return back()->with('success', 'MPS schedule deleted.');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['line_id' => 'required|exists:sewing_lines,id', 'planned_start_date' => 'required|date', 'planned_end_date' => 'required|date|after_or_equal:planned_start_date', 'daily_target_qty' => 'required|integer|min:1']);
        $schedule = DB::table('mps_schedules')->find($id);
        if (!$schedule) abort(404);
        if (DB::table('daily_production_logs')->where('mps_schedule_id', $id)->exists()) return back()->with('error', 'A schedule with production logs cannot be changed.');
        $line = DB::table('sewing_lines')->find($data['line_id']);
        if (!$line) throw ValidationException::withMessages(['line_id' => ['The selected sewing line is unavailable.']]);
        $workOrder = DB::table('work_orders')->find($schedule->work_order_id);
        $days = max(1, \Carbon\Carbon::parse($data['planned_start_date'])->diffInDays(\Carbon\Carbon::parse($data['planned_end_date'])) + 1);
        $scheduledMinutes = DB::table('mps_schedules')->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')->where('mps_schedules.id', '!=', $id)->where('mps_schedules.line_id', $data['line_id'])->whereDate('mps_schedules.planned_start_date', '<=', $data['planned_end_date'])->whereDate('mps_schedules.planned_end_date', '>=', $data['planned_start_date'])->sum(DB::raw('mps_schedules.daily_target_qty * work_orders.smv'));
        if ($scheduledMinutes + ($data['daily_target_qty'] * $days * $workOrder->smv) > $days * $line->default_workers * $line->working_hours_per_day * 60) throw ValidationException::withMessages(['daily_target_qty' => ['Schedule exceeds available line capacity.']]);
        $scheduledQty = DB::table('mps_schedules')->where('work_order_id', $workOrder->id)->where('id', '!=', $id)->sum(DB::raw('daily_target_qty * (DATEDIFF(COALESCE(planned_end_date, planned_start_date), planned_start_date) + 1)'));
        if ($scheduledQty + $data['daily_target_qty'] * $days > $workOrder->planned_qty) throw ValidationException::withMessages(['daily_target_qty' => ['Scheduled quantity exceeds the work order planned quantity.']]);
        $lastEta = DB::table('po_items')->join('mrp_suggestions', 'po_items.mrp_suggestion_id', '=', 'mrp_suggestions.id')->where('mrp_suggestions.cutsheet_id', $workOrder->cutsheet_id)->max('po_items.expected_date');
        if ($lastEta && $data['planned_start_date'] < $lastEta) throw ValidationException::withMessages(['planned_start_date' => ['Planned Start Date cannot be before the last material ETA.']]);
        DB::table('mps_schedules')->where('id', $id)->update($data + ['material_eta_status' => $lastEta ? 'ready' : 'unknown', 'updated_at' => now()]);
        return back()->with('success', 'MPS schedule updated.');
    }
}
