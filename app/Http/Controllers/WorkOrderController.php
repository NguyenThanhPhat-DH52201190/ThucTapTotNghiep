<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkOrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['cutsheet_id' => 'required|exists:ocs,id', 'planned_qty' => 'required|integer|min:1', 'smv' => 'required|numeric|min:0.01']);
        DB::transaction(function () use ($data) {
            $order = DB::table('ocs')->where('id', $data['cutsheet_id'])->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['closed', 'cancelled'], true)) throw ValidationException::withMessages(['cutsheet_id' => ['Order is closed or cancelled.']]);
            $allocated = DB::table('work_orders')->where('cutsheet_id', $order->id)->where('status', '!=', 'cancelled')->sum('planned_qty');
            if ($allocated + $data['planned_qty'] > $order->Qty) throw ValidationException::withMessages(['planned_qty' => ['Planned quantity exceeds the remaining order quantity.']]);
            DB::table('work_orders')->insert($data + ['wo_number' => 'WO-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(4)), 'status' => 'planned', 'created_at' => now(), 'updated_at' => now()]);
        });
        return $request->expectsJson() ? response()->json(['success' => true], 201) : back()->with('success', 'Work order created.');
    }

    public function split(Request $request, int $id)
    {
        $data = $request->validate(['planned_qty' => 'required|integer|min:1']);
        DB::transaction(function () use ($id, $data) {
            $wo = DB::table('work_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($wo->status !== 'planned') throw ValidationException::withMessages(['planned_qty' => ['Only a planned work order can be split.']]);
            if ($data['planned_qty'] >= $wo->planned_qty) throw ValidationException::withMessages(['planned_qty' => ['Split quantity must be less than the original quantity.']]);
            DB::table('work_orders')->where('id', $wo->id)->update(['planned_qty' => $wo->planned_qty - $data['planned_qty'], 'updated_at' => now()]);
            DB::table('work_orders')->insert(['wo_number' => 'WO-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(4)), 'cutsheet_id' => $wo->cutsheet_id, 'planned_qty' => $data['planned_qty'], 'smv' => $wo->smv, 'status' => 'planned', 'created_at' => now(), 'updated_at' => now()]);
        });
        return back()->with('success', 'Work order split.');
    }

    public function status(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:planned,released,in_production,completed,cancelled']);
        $wo = DB::table('work_orders')->find($id); abort_unless($wo, 404);
        $allowed = ['planned' => ['released', 'cancelled'], 'released' => ['in_production', 'cancelled'], 'in_production' => ['completed'], 'completed' => [], 'cancelled' => []];
        if ($wo->status !== $data['status'] && !in_array($data['status'], $allowed[$wo->status] ?? [], true)) throw ValidationException::withMessages(['status' => ["Invalid transition from {$wo->status}."]]);
        DB::table('work_orders')->where('id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);
        return back()->with('success', 'Work order status updated.');
    }
}
