<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class MasterPlanController extends Controller
{
    public function index(Request $request)
    {
        $plan = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->when($request->filled('to_date'), function ($query) use ($request) {
                $query->whereDate('mtp.Rdate', $request->to_date);
            })
            ->when($request->filled('po'), function ($query) use ($request) {
                $query->where('ocs.ONum', 'like', '%' . $request->po . '%');
            })
            ->when($request->filled('style'), function ($query) use ($request) {
                $query->where('ocs.SNo', 'like', '%' . $request->style . '%');
            })
            ->select(
                'mtp.*',
                'ocs.SNo as Style',
                'ocs.ONum as PO'
            )
            ->orderBy('mtp.Line', 'asc')
            ->get();

        // 🔥 DANH SÁCH NGÀY LỄ
        $holidays = DB::table('holidays')
            ->pluck('holiday')
            ->toArray();

        // Ưu tiên line màu lên trên, line khác xuống dưới.
        $colorLinePriority = [
            'blue' => 1,
            'yellow' => 2,
            'green' => 3,
            'orange' => 4,
        ];

        $plan = collect($plan)
            ->sort(function ($a, $b) use ($colorLinePriority) {
                $lineA = strtolower((string) ($a->Line ?? ''));
                $lineB = strtolower((string) ($b->Line ?? ''));

                $isColorA = array_key_exists($lineA, $colorLinePriority);
                $isColorB = array_key_exists($lineB, $colorLinePriority);

                // Line màu luôn đứng trước line không màu.
                if ($isColorA !== $isColorB) {
                    return $isColorA ? -1 : 1;
                }

                // Trong cùng nhóm (màu hoặc không màu), ưu tiên FirstOPT tăng dần.
                $dateCompare = strcmp((string) ($a->FirstOPT ?? ''), (string) ($b->FirstOPT ?? ''));
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                $rankA = $colorLinePriority[$lineA] ?? 999;
                $rankB = $colorLinePriority[$lineB] ?? 999;

                // Nếu cùng ngày thì áp dụng ưu tiên thứ tự line màu.
                if ($isColorA && $rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                // Cuối cùng sort theo tên line để kết quả ổn định.
                if ($lineA !== $lineB) {
                    return $lineA <=> $lineB;
                }

                return ((int) ($a->id ?? 0)) <=> ((int) ($b->id ?? 0));
            })
            ->values();

        // 🔥 GROUP THEO LINE
        $grouped = $plan->groupBy('Line');

        foreach ($grouped as $line => $items) {

            $previousFinish = null;

            foreach ($items as $item) {

                // 🔹 FirstOPT
                if (!$previousFinish) {
                    $firstOPT = $item->FirstOPT
                        ? Carbon::parse($item->FirstOPT)
                        : null;
                } else {
                    $firstOPT = $this->calcExFact($previousFinish, 1, $holidays);
                }

                // 🔹 Nếu không có FirstOPT thì bỏ qua
                if (!$firstOPT || !$item->lt) {
                    $item->calc_FirstOPT = $firstOPT;
                    $item->calc_Finish_SEW = null;
                    $item->calc_EX_Fact = null;
                    continue;
                }

                $finishSew = $this->calcFinishSew($firstOPT, $item->lt, $holidays);
                $exFact    = $this->calcExFact($finishSew, 3, $holidays);

                // 👉 GÁN RA VIEW
                $item->calc_FirstOPT = $firstOPT;
                $item->calc_Finish_SEW = $finishSew;
                $item->calc_EX_Fact = $exFact;

                $previousFinish = $finishSew;
            }
        }

        return view('admin.masterplan.masterplan', compact('plan'));
    }

    public function create()
    {
        $ocs = DB::table('ocs')->get();
        return view('admin.masterplan.addmaster', compact('ocs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'CU' => 'required',
            'Line' => 'required',
            'Rdate' => 'required|date',
            'ETADate' => 'nullable|date',
            'ActDate' => 'nullable|date',
            'lt' => 'nullable|integer|min:0',
            'FirstOPT' => 'nullable|date',
            'Qty_dis' => 'nullable|integer|min:0',
        ]);

        $ocs = DB::table('ocs')->where('CS', $request->CU)->first();

        if (!$ocs) {
            return back()->withErrors(['CU' => 'CS không tồn tại trong OCS'])->withInput();
        }

        // tính tổng Qty_dis hiện tại của CU
        $totalQtyDis = DB::table('mtp')
            ->where('CU', $request->CU)
            ->sum('Qty_dis');

        // tổng mới sau khi thêm
        $newTotal = $totalQtyDis + ($request->Qty_dis ?? 0);

        if ($newTotal > $ocs->Qty) {
            return back()->withErrors([
                'Qty_dis' => 'Tổng Qty_dis (' . $newTotal . ') vượt quá Qty OCS (' . $ocs->Qty . ')'
            ])->withInput();
        }

        DB::table('mtp')->insert([
            'CU' => $request->CU,
            'Line' => $request->Line,
            'Rdate' => $request->Rdate,
            'ETADate' => $request->ETADate,
            'ActDate' => $request->ActDate,
            'lt' => $request->lt,
            'FirstOPT' => $request->FirstOPT,
            'Qty_dis' => $request->Qty_dis,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterplan.index')
            ->with('success', 'Saved successfully');
    }

    public function edit(string $id)
    {
        $plan = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->select(
                'mtp.*',
                'ocs.SNo as Style',
                'ocs.ONum as PO'
            )
            ->where('mtp.id', $id)
            ->first();

        return view('admin.masterplan.editmaster', compact('plan'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'CU' => 'required',
            'Line' => 'required',
            'Rdate' => 'required|date',
            'ETADate' => 'nullable|date',
            'ActDate' => 'nullable|date',
            'lt' => 'nullable|integer|min:0',
            'FirstOPT' => 'nullable|date',
            'Qty_dis' => 'nullable|integer|min:0',
        ], [
            'CU.unique' => 'CU đã tồn tại!',
        ]);

        $ocs = DB::table('ocs')->where('CS', $request->CU)->first();

        if (!$ocs) {
            return back()->withErrors(['CU' => 'CS không tồn tại trong OCS'])->withInput();
        }

        // tổng Qty_dis trừ bản ghi hiện tại
        $totalQtyDis = DB::table('mtp')
            ->where('CU', $request->CU)
            ->where('id', '!=', $id)
            ->sum('Qty_dis');

        // cộng lại với giá trị mới
        $newTotal = $totalQtyDis + ($request->Qty_dis ?? 0);

        if ($newTotal > $ocs->Qty) {
            return back()->withErrors([
                'Qty_dis' => 'Tổng Qty_dis (' . $newTotal . ') vượt quá Qty OCS (' . $ocs->Qty . ')'
            ])->withInput();
        }

        DB::table('mtp')->where('id', $id)->update([
            'CU' => $request->CU,
            'Line' => $request->Line,
            'Rdate' => $request->Rdate,
            'ETADate' => $request->ETADate,
            'ActDate' => $request->ActDate,
            'lt' => $request->lt,
            'FirstOPT' => $request->FirstOPT,
            'Qty_dis' => $request->Qty_dis,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterplan.index', [
            'role' => 'admin',
            'page' => 'masterplan'
        ])->with('success', 'Updated successfully');
    }

    public function destroy(string $id)
    {
        $plan = DB::table('mtp')->where('id', $id)->first();

        if (!$plan) {
            return redirect()->back()->with('error', 'Data not found');
        }

        DB::table('mtp')->where('id', $id)->delete();

        return redirect()->back()
            ->with('success', 'Deleted successfully');
    }

    public function calcDateAjax(Request $request)
    {
        $request->validate([
            'firstOPT' => 'required|date',
            'lt' => 'required|integer|min:0',
        ]);

        $holidays = DB::table('holidays')
            ->pluck('holiday')
            ->toArray();

        $finish = $this->calcFinishSew($request->firstOPT, (int) $request->lt, $holidays);
        $ex = $this->calcExFact($finish, 3, $holidays);

        return response()->json([
            'finish' => $finish ? $finish->toDateString() : null,
            'ex' => $ex ? $ex->toDateString() : null,
        ]);
    }

    public function calcFinishSew($startDate, $days, $holidays = [])
    {
        $start = Carbon::parse($startDate);

        $extra = 0;

        for ($i = 0; $i <= $days; $i++) { // ✅ include start
            $current = $start->copy()->addDays($i);

            if ($current->isSunday()) {
                $extra++;
            }

            if (in_array($current->toDateString(), $holidays)) {
                $extra++;
            }
        }

        return $start->copy()->addDays($days + $extra);
    }

    public function calcExFact($startDate, $days, $holidays = [])
    {
        $start = Carbon::parse($startDate);

        $extra = 0;

        for ($i = 1; $i <= $days; $i++) { // ✅ bỏ start
            $current = $start->copy()->addDays($i);

            if ($current->isSunday()) {
                $extra++;
            }

            if (in_array($current->toDateString(), $holidays)) {
                $extra++;
            }
        }

        return $start->copy()->addDays($days + $extra);
    }
}
