<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class MasterPlanController extends Controller
{
    private function nullableDate($value): ?string
    {
        return filled($value) ? $value : null;
    }

    private function nullableInteger($value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    private function getMasterPlan(Request $request): Collection
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

        $holidays = DB::table('holidays')
            ->pluck('holiday')
            ->toArray();

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

                if ($isColorA !== $isColorB) {
                    return $isColorA ? -1 : 1;
                }

                $dateCompare = strcmp((string) ($a->FirstOPT ?? ''), (string) ($b->FirstOPT ?? ''));
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                $rankA = $colorLinePriority[$lineA] ?? 999;
                $rankB = $colorLinePriority[$lineB] ?? 999;

                if ($isColorA && $rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                if ($lineA !== $lineB) {
                    return $lineA <=> $lineB;
                }

                return ((int) ($a->id ?? 0)) <=> ((int) ($b->id ?? 0));
            })
            ->values();

        $grouped = $plan->groupBy('Line');

        foreach ($grouped as $items) {
            $previousFinish = null;

            foreach ($items as $item) {
                if (!$previousFinish) {
                    $firstOPT = $item->FirstOPT
                        ? Carbon::parse($item->FirstOPT)
                        : null;
                } else {
                    $firstOPT = $this->calcExFact($previousFinish, 1, $holidays);
                }

                if (!$firstOPT || !$item->lt) {
                    $item->calc_FirstOPT = $firstOPT;
                    $item->calc_Finish_SEW = null;
                    $item->calc_EX_Fact = null;
                    continue;
                }

                $finishSew = $this->calcFinishSew($firstOPT, $item->lt, $holidays);
                $exFact = $this->calcExFact($finishSew, 3, $holidays);

                $item->calc_FirstOPT = $firstOPT;
                $item->calc_Finish_SEW = $finishSew;
                $item->calc_EX_Fact = $exFact;

                $previousFinish = $finishSew;
            }
        }

        return $plan;
    }

    public function index(Request $request)
    {
        $plan = $this->getMasterPlan($request);

        return view('admin.masterplan.masterplan', compact('plan'));
    }

    public function export(Request $request)
    {
        $plan = $this->getMasterPlan($request);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('MasterPlan');

        $headers = [
            'CU',
            'Line',
            'Style',
            'PO',
            'Qty_dis',
            'Rdate',
            'ETADate',
            'ActDate',
            'LT',
            'FirstOPT',
            'Finish_SEW',
            'EX_Fact',
        ];

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . '1', $header);
        }

        $rowIndex = 2;
        foreach ($plan as $item) {
            $sheet->setCellValue('A' . $rowIndex, $item->CU ?? '');
            $sheet->setCellValue('B' . $rowIndex, $item->Line ?? '');
            $sheet->setCellValue('C' . $rowIndex, $item->Style ?? '');
            $sheet->setCellValue('D' . $rowIndex, $item->PO ?? '');
            $sheet->setCellValue('E' . $rowIndex, $item->Qty_dis ?? '');
            $sheet->setCellValue('F' . $rowIndex, $item->Rdate ?? '');
            $sheet->setCellValue('G' . $rowIndex, $item->ETADate ?? '');
            $sheet->setCellValue('H' . $rowIndex, $item->ActDate ?? '');
            $sheet->setCellValue('I' . $rowIndex, $item->lt ?? '');
            $sheet->setCellValue('J' . $rowIndex, $item->calc_FirstOPT ? $item->calc_FirstOPT->format('Y-m-d') : '');
            $sheet->setCellValue('K' . $rowIndex, $item->calc_Finish_SEW ? $item->calc_Finish_SEW->format('Y-m-d') : '');
            $sheet->setCellValue('L' . $rowIndex, $item->calc_EX_Fact ? $item->calc_EX_Fact->format('Y-m-d') : '');
            $rowIndex++;
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'masterplan-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
            'LineColor' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}){1,2}$/'],
            'Rdate' => 'nullable|date',
            'ETADate' => 'nullable|date',
            'ActDate' => 'nullable|date',
            'lt' => 'nullable|integer|min:0',
            'FirstOPT' => 'nullable|date',
            'Qty_dis' => 'nullable|integer|min:0',
        ]);

        $ocs = DB::table('ocs')->where('CS', $request->CU)->first();

        if (!$ocs) {
            return back()->withErrors(['CU' => 'CS not found in OCS'])->withInput();
        }

        // total Qty_dis for current CU
        $totalQtyDis = DB::table('mtp')
            ->where('CU', $request->CU)
            ->sum('Qty_dis');

        // new total after adding
        $newTotal = $totalQtyDis + ($request->Qty_dis ?? 0);

        if ($newTotal > $ocs->Qty) {
            return back()->withErrors([
                'Qty_dis' => 'Total Qty_dis (' . $newTotal . ') exceeds OCS Qty (' . $ocs->Qty . ')'
            ])->withInput();
        }

        try {
            DB::table('mtp')->insert([
                'CU' => $request->CU,
                'Line' => $request->Line,
                'LineColor' => $request->LineColor,
                'Rdate' => $this->nullableDate($request->Rdate),
                'ETADate' => $this->nullableDate($request->ETADate),
                'ActDate' => $this->nullableDate($request->ActDate),
                'lt' => $this->nullableInteger($request->lt),
                'FirstOPT' => $this->nullableDate($request->FirstOPT),
                'Qty_dis' => $this->nullableInteger($request->Qty_dis),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.masterplan.index')
                ->with('success', 'Saved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to create MasterPlan record', [
                'message' => $e->getMessage(),
                'input' => $request->except(['_token']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to save the record. Please check your input and try again.');
        }
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
            'LineColor' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}){1,2}$/'],
            'Rdate' => 'nullable|date',
            'ETADate' => 'nullable|date',
            'ActDate' => 'nullable|date',
            'lt' => 'nullable|integer|min:0',
            'FirstOPT' => 'nullable|date',
            'Qty_dis' => 'nullable|integer|min:0',
        ], [
            'CU.unique' => 'CU already exists!',
        ]);

        $ocs = DB::table('ocs')->where('CS', $request->CU)->first();

        if (!$ocs) {
            return back()->withErrors(['CU' => 'CS not found in OCS'])->withInput();
        }

        // total Qty_dis excluding current record
        $totalQtyDis = DB::table('mtp')
            ->where('CU', $request->CU)
            ->where('id', '!=', $id)
            ->sum('Qty_dis');

        // add with new value
        $newTotal = $totalQtyDis + ($request->Qty_dis ?? 0);

        if ($newTotal > $ocs->Qty) {
            return back()->withErrors([
                'Qty_dis' => 'Total Qty_dis (' . $newTotal . ') exceeds OCS Qty (' . $ocs->Qty . ')'
            ])->withInput();
        }

        try {
            DB::table('mtp')->where('id', $id)->update([
                'CU' => $request->CU,
                'Line' => $request->Line,
                'LineColor' => $request->LineColor,
                'Rdate' => $this->nullableDate($request->Rdate),
                'ETADate' => $this->nullableDate($request->ETADate),
                'ActDate' => $this->nullableDate($request->ActDate),
                'lt' => $this->nullableInteger($request->lt),
                'FirstOPT' => $this->nullableDate($request->FirstOPT),
                'Qty_dis' => $this->nullableInteger($request->Qty_dis),
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.masterplan.index', [
                'role' => 'admin',
                'page' => 'masterplan'
            ])->with('success', 'Updated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to update MasterPlan record', [
                'message' => $e->getMessage(),
                'id' => $id,
                'input' => $request->except(['_token', '_method']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update the record. Please check your input and try again.');
        }
    }

    public function destroy(string $id)
    {
        try {
            $plan = DB::table('mtp')->where('id', $id)->first();

            if (!$plan) {
                return redirect()->back()->with('error', 'Record not found.');
            }

            DB::table('mtp')->where('id', $id)->delete();

            return redirect()->back()
                ->with('success', 'Deleted successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to delete MasterPlan record', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return redirect()->back()
                ->with('error', 'Unable to delete the record. Please try again.');
        }
    }

    public function calcDateAjax(Request $request)
    {
        $request->validate([
            'firstOPT' => 'nullable|date',
            'lt' => 'nullable|integer|min:0',
        ]);

        if (!$request->filled('firstOPT') || !$request->filled('lt')) {
            return response()->json([
                'finish' => null,
                'ex' => null,
            ]);
        }

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

        for ($i = 1; $i <= $days; $i++) { // Skip the start day
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
