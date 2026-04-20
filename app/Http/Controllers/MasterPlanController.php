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
    private function isAdmin(Request $request): bool
    {
        return $request->user()?->role === 'admin';
    }

    private function redirectRouteForRole(Request $request): string
    {
        return $this->isAdmin($request)
            ? 'admin.masterplan.index'
            : 'masterplan.view';
    }

    private function nullableDate($value): ?string
    {
        return filled($value) ? $value : null;
    }

    private function nullableInteger($value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    private function skipSunday(Carbon $date): Carbon
    {
        $result = $date->copy();
        if ($result->isSunday()) {
            $result->addDay();
        }
        return $result;
    }

    private function getMasterPlan(Request $request): Collection
    {
        $lineCateByName = DB::table('colors')
            ->select('name', 'cate')
            ->where('is_active', 1)
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    strtolower(trim((string) $item->name)) => strtoupper((string) ($item->cate ?? 'GSV')),
                ];
            })
            ->all();

        $plan = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->when($request->filled('to_date'), function ($query) use ($request) {
                $query->whereDate('mtp.ETA1', $request->to_date);
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
            ->sort(function ($a, $b) use ($colorLinePriority, $lineCateByName) {
                $lineA = strtolower((string) ($a->Line ?? ''));
                $lineB = strtolower((string) ($b->Line ?? ''));

                $cateA = $lineCateByName[$lineA] ?? 'SUBCON';
                $cateB = $lineCateByName[$lineB] ?? 'SUBCON';
                $isColorA = $cateA === 'GSV';
                $isColorB = $cateB === 'GSV';

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

        foreach ($plan as $item) {
            $lineKey = strtolower(trim((string) ($item->Line ?? '')));
            $item->LineCate = $lineCateByName[$lineKey] ?? 'SUBCON';
        }

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
                $finishSew = $this->skipSunday($finishSew);
                $exFact = $this->calcExFact($finishSew, 3, $holidays);

                $item->calc_FirstOPT = $firstOPT;
                $item->calc_Finish_SEW = $finishSew;
                $item->calc_EX_Fact = $exFact;

                $previousFinish = $finishSew;
            }
        }

        // Calculate ShipBalance for each item
        foreach ($plan as $item) {
            if ($item->ExQty === null) {
                $item->ShipBalance = null;
            } else {
                $qtyDis = $item->Qty_dis ?? 0;
                $item->ShipBalance = $qtyDis - $item->ExQty;
            }
        }

        // Filter to show only items with ShipBalance if requested
        if ($request->filled('ship_balance_only') && $request->ship_balance_only == 1) {
            $plan = $plan->filter(function ($item) {
                return $item->ShipBalance !== null && $item->ShipBalance > 0;
            });
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
            'Fabric1',
            'ETA1',
            'Actual',
            'Fabric2',
            'ETA2',
            'Linning',
            'ETA3',
            'Pocket',
            'ETA4',
            'Trim',
            'inWHDate',
            '3rd_PartyInspection',
            'ShipDate2',
            'SoTK',
            'ExQty',
            'ShipBalance',
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
            $rowValues = [
                $item->CU ?? '',
                $item->Line ?? '',
                $item->Style ?? '',
                $item->PO ?? '',
                $item->Qty_dis ?? '',
                $item->Fabric1 ?? '',
                $item->ETA1 ?? '',
                $item->Actual ?? '',
                $item->Fabric2 ?? '',
                $item->ETA2 ?? '',
                $item->Linning ?? '',
                $item->ETA3 ?? '',
                $item->Pocket ?? '',
                $item->ETA4 ?? '',
                $item->Trim ?? '',
                $item->inWHDate ?? '',
                $item->{'3rd_PartyInspection'} ?? '',
                $item->ShipDate2 ?? '',
                $item->SoTK ?? '',
                $item->ExQty ?? '',
                $item->ShipBalance ?? '',
                $item->lt ?? '',
                $item->calc_FirstOPT ? $item->calc_FirstOPT->format('Y-m-d') : '',
                $item->calc_Finish_SEW ? $item->calc_Finish_SEW->format('Y-m-d') : '',
                $item->calc_EX_Fact ? $item->calc_EX_Fact->format('Y-m-d') : '',
            ];

            foreach ($rowValues as $columnIndex => $value) {
                $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
                $sheet->setCellValue($column . $rowIndex, $value);
            }

            $rowIndex++;
        }

        for ($columnIndex = 1; $columnIndex <= count($headers); $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
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
        $ocs = DB::table('ocs')
            ->orderBy('CS', 'asc')
            ->get();

        $colors = DB::table('colors')
            ->select('name', 'hex_code')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('admin.masterplan.addmaster', compact('ocs', 'colors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'CU' => 'required',
            'Line' => 'required',
            'LineColor' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}){1,2}$/'],
            'Fabric1' => 'nullable|string|max:50',
            'ETA1' => 'nullable|date',
            'Actual' => 'nullable|date',
            'Fabric2' => 'nullable|string|max:50',
            'ETA2' => 'nullable|date',
            'Linning' => 'nullable|string|max:50',
            'ETA3' => 'nullable|date',
            'Pocket' => 'nullable|string|max:50',
            'ETA4' => 'nullable|date',
            'Trim' => 'nullable|string|max:50',
            'inWHDate' => 'nullable|date',
            '3rd_PartyInspection' => 'nullable|string|max:50',
            'ShipDate2' => 'nullable|date',
            'SoTK' => 'nullable|string|max:50',
            'ExQty' => [
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if (!filled($value) || !filled($request->Qty_dis)) {
                        return;
                    }

                    if ((int) $value > (int) $request->Qty_dis) {
                        $fail('ExQty cannot be greater than Qty_dis.');
                    }
                },
            ],
            'lt' => 'nullable|integer|min:0',
            'FirstOPT' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value && Carbon::parse($value)->isSunday()) {
                        $fail('FirstOPT cannot be on a Sunday. Please choose another date.');
                    }
                }
            ],
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
                'Fabric1' => filled($request->Fabric1) ? $request->Fabric1 : null,
                'ETA1' => $this->nullableDate($request->ETA1),
                'Actual' => $this->nullableDate($request->Actual),
                'Fabric2' => filled($request->Fabric2) ? $request->Fabric2 : null,
                'ETA2' => $this->nullableDate($request->ETA2),
                'Linning' => filled($request->Linning) ? $request->Linning : null,
                'ETA3' => $this->nullableDate($request->ETA3),
                'Pocket' => filled($request->Pocket) ? $request->Pocket : null,
                'ETA4' => $this->nullableDate($request->ETA4),
                'Trim' => filled($request->Trim) ? $request->Trim : null,
                'inWHDate' => $this->nullableDate($request->inWHDate),
                '3rd_PartyInspection' => filled($request->input('3rd_PartyInspection')) ? $request->input('3rd_PartyInspection') : null,
                'ShipDate2' => $this->nullableDate($request->ShipDate2),
                'SoTK' => filled($request->SoTK) ? $request->SoTK : null,
                'ExQty' => $this->nullableInteger($request->ExQty),
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
                'ocs.ONum as PO',
                'ocs.Qty'
            )
            ->where('mtp.id', $id)
            ->first();

        $colors = DB::table('colors')
            ->select('name', 'hex_code')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $fabricOnly = false;
        $updateRoute = route('admin.masterplan.update', $id);

        return view('admin.masterplan.editmaster', compact('plan', 'fabricOnly', 'updateRoute', 'colors'));
    }

    public function editFabric(string $id)
    {
        $plan = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->select(
                'mtp.*',
                'ocs.SNo as Style',
                'ocs.ONum as PO',
                'ocs.Qty'
            )
            ->where('mtp.id', $id)
            ->first();

        if (!$plan) {
            return redirect()->route('masterplan.view')
                ->with('error', 'Record not found.');
        }

        $colors = DB::table('colors')
            ->select('name', 'hex_code')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $fabricOnly = request()->user()?->role === 'ppic';
        $updateRoute = route('masterplan.fabric.update', $id);

        return view('admin.masterplan.editmaster', compact('plan', 'fabricOnly', 'updateRoute', 'colors'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'CU' => 'required',
            'Line' => 'required',
            'LineColor' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}){1,2}$/'],
            'Fabric1' => 'nullable|string|max:50',
            'ETA1' => 'nullable|date',
            'Actual' => 'nullable|date',
            'Fabric2' => 'nullable|string|max:50',
            'ETA2' => 'nullable|date',
            'Linning' => 'nullable|string|max:50',
            'ETA3' => 'nullable|date',
            'Pocket' => 'nullable|string|max:50',
            'ETA4' => 'nullable|date',
            'Trim' => 'nullable|string|max:50',
            'inWHDate' => 'nullable|date',
            '3rd_PartyInspection' => 'nullable|string|max:50',
            'ShipDate2' => 'nullable|date',
            'SoTK' => 'nullable|string|max:50',
            'ExQty' => [
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if (!filled($value) || !filled($request->Qty_dis)) {
                        return;
                    }

                    if ((int) $value > (int) $request->Qty_dis) {
                        $fail('ExQty cannot be greater than Qty_dis.');
                    }
                },
            ],
            'lt' => 'nullable|integer|min:0',
            'FirstOPT' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value && Carbon::parse($value)->isSunday()) {
                        $fail('FirstOPT cannot be on a Sunday. Please choose another date.');
                    }
                }
            ],
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
                'Fabric1' => filled($request->Fabric1) ? $request->Fabric1 : null,
                'ETA1' => $this->nullableDate($request->ETA1),
                'Actual' => $this->nullableDate($request->Actual),
                'Fabric2' => filled($request->Fabric2) ? $request->Fabric2 : null,
                'ETA2' => $this->nullableDate($request->ETA2),
                'Linning' => filled($request->Linning) ? $request->Linning : null,
                'ETA3' => $this->nullableDate($request->ETA3),
                'Pocket' => filled($request->Pocket) ? $request->Pocket : null,
                'ETA4' => $this->nullableDate($request->ETA4),
                'Trim' => filled($request->Trim) ? $request->Trim : null,
                'inWHDate' => $this->nullableDate($request->inWHDate),
                '3rd_PartyInspection' => filled($request->input('3rd_PartyInspection')) ? $request->input('3rd_PartyInspection') : null,
                'ShipDate2' => $this->nullableDate($request->ShipDate2),
                'SoTK' => filled($request->SoTK) ? $request->SoTK : null,
                'ExQty' => $this->nullableInteger($request->ExQty),
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

    public function updateFabric(Request $request, string $id)
    {
        $plan = DB::table('mtp')->where('id', $id)->first();

        if (!$plan) {
            return redirect()->route($this->redirectRouteForRole($request))
                ->with('error', 'Record not found.');
        }

        $validated = $request->validate([
            'Fabric1' => 'nullable|string|max:50',
            'ETA1' => 'nullable|date',
            'Actual' => 'nullable|date',
            'Fabric2' => 'nullable|string|max:50',
            'ETA2' => 'nullable|date',
            'Linning' => 'nullable|string|max:50',
            'ETA3' => 'nullable|date',
            'Pocket' => 'nullable|string|max:50',
            'ETA4' => 'nullable|date',
            'Trim' => 'nullable|string|max:50',
        ]);

        try {
            DB::table('mtp')->where('id', $id)->update([
                'Fabric1' => filled($validated['Fabric1'] ?? null) ? $validated['Fabric1'] : null,
                'ETA1' => $this->nullableDate($validated['ETA1'] ?? null),
                'Actual' => $this->nullableDate($validated['Actual'] ?? null),
                'Fabric2' => filled($validated['Fabric2'] ?? null) ? $validated['Fabric2'] : null,
                'ETA2' => $this->nullableDate($validated['ETA2'] ?? null),
                'Linning' => filled($validated['Linning'] ?? null) ? $validated['Linning'] : null,
                'ETA3' => $this->nullableDate($validated['ETA3'] ?? null),
                'Pocket' => filled($validated['Pocket'] ?? null) ? $validated['Pocket'] : null,
                'ETA4' => $this->nullableDate($validated['ETA4'] ?? null),
                'Trim' => filled($validated['Trim'] ?? null) ? $validated['Trim'] : null,
                'updated_at' => now(),
            ]);

            return redirect()->route($this->redirectRouteForRole($request))
                ->with('success', 'Updated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to update Fabric-to-Trim fields', [
                'message' => $e->getMessage(),
                'id' => $id,
                'role' => $request->user()?->role,
                'input' => $request->except(['_token', '_method']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update the selected fields. Please try again.');
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
        $finish = $this->skipSunday($finish);
        $ex = $this->calcExFact($finish, 3, $holidays);

        return response()->json([
            'finish' => $finish ? $finish->toDateString() : null,
            'ex' => $ex ? $ex->toDateString() : null,
        ]);
    }

    private function normalizeHolidaySet(array $holidays): array
    {
        $holidaySet = [];

        foreach ($holidays as $holiday) {
            if ($holiday === null) {
                continue;
            }

            $date = substr((string) $holiday, 0, 10);
            if ($date !== '') {
                $holidaySet[$date] = true;
            }
        }

        return $holidaySet;
    }

    private function countNonWorkingDays(Carbon $start, Carbon $end, array $holidaySet, bool $includeStart): int
    {
        $cursor = $includeStart
            ? $start->copy()
            : $start->copy()->addDay();

        $count = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($cursor->isSunday() || isset($holidaySet[$cursor->toDateString()])) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    public function calcFinishSew($startDate, $days, $holidays = [])
    {
        $start = Carbon::parse($startDate);
        $baseDays = max(0, (int) $days);

        if ($baseDays === 0) {
            return $start;
        }

        $holidaySet = $this->normalizeHolidaySet($holidays);
        $totalDays = $baseDays - 1;

        while (true) {
            $end = $start->copy()->addDays($totalDays);
            $extra = $this->countNonWorkingDays($start, $end, $holidaySet, true);
            $newTotalDays = $baseDays - 1 + $extra;

            if ($newTotalDays === $totalDays) {
                return $end;
            }

            $totalDays = $newTotalDays;
        }
    }

    public function calcExFact($startDate, $days, $holidays = [])
    {
        $start = Carbon::parse($startDate);
        $baseDays = max(0, (int) $days);
        $holidaySet = $this->normalizeHolidaySet($holidays);
        $totalDays = $baseDays;

        while (true) {
            $end = $start->copy()->addDays($totalDays);
            $extra = $this->countNonWorkingDays($start, $end, $holidaySet, false);
            $newTotalDays = $baseDays + $extra;

            if ($newTotalDays === $totalDays) {
                return $end;
            }

            $totalDays = $newTotalDays;
        }
    }
}
