<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\RevenueMasterPlanSync;
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
            ->leftJoin('bom_headers', 'ocs.bom_header_id', '=', 'bom_headers.id')
            ->when($request->filled('mps_status'), function ($query) use ($request) {
                $query->where('mtp.mps_status', $request->mps_status);
            })
            ->when($request->filled('line'), function ($query) use ($request) {
                $query->where('mtp.Line', $request->line);
            })
            ->select(
                'mtp.*',
                'ocs.id as image_ocs_id',
                DB::raw(\App\Services\CustomerStyleService::imageSql('ocs', 'SNo').' as ocs_image_path'),
                'ocs.SNo as Style',
                'ocs.ONum as PO',
                'ocs.Qty as Order_Qty',
                'ocs.CMT as CMT',
                'ocs.status as order_status',
                'ocs.bom_header_id',
                'bom_headers.style_no as bom_style',
                'bom_headers.version as bom_version',
                'bom_headers.total_fabric_cost',
                'bom_headers.total_trim_cost'
            )
            ->orderBy('mtp.Line', 'asc')
            ->get();

        $holidays = DB::table('holidays')
            ->pluck('holiday')
            ->toArray();

        $colorLinePriority = [
            'pending cs' => 0,
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

                $rankA = $colorLinePriority[$lineA] ?? 999;
                $rankB = $colorLinePriority[$lineB] ?? 999;

                if ($isColorA && $rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                $dateCompare = strcmp((string) ($a->FirstOPT ?? ''), (string) ($b->FirstOPT ?? ''));
                if ($dateCompare !== 0) {
                    return $dateCompare;
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
                $isSubcon = strtoupper((string) ($item->LineCate ?? 'SUBCON')) !== 'GSV';

                if ($isSubcon) {
                    // Subcon keeps manual FirstOPT per row and does not follow line chain.
                    $firstOPT = $item->FirstOPT
                        ? Carbon::parse($item->FirstOPT)
                        : null;
                } elseif (!$previousFinish) {
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

                if (!$isSubcon) {
                    $previousFinish = $finishSew;
                }
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

        // Default behavior: hide only rows that were processed and computed to zero.
        // QA/QC works from the complete Master Plan. The ship-balance filter is
        // only a convenience for the planning views and must not hide MTP rows.
        $shipBalanceOnly = $request->user()?->role === 'qa_qc'
            ? 0
            : (int) $request->input('ship_balance_only', 1);
        if ($shipBalanceOnly === 1) {
            $plan = $plan->filter(function ($item) {
                if ($item->ExQty === null) {
                    return true;
                }

                return (float) ($item->ShipBalance ?? 0) !== 0.0;
            });
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $plan = $plan->filter(function ($item) use ($search) {
                foreach (get_object_vars($item) as $value) {
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d');
                    } elseif (!is_scalar($value)) {
                        continue;
                    }

                    if (mb_stripos((string) $value, $search) !== false) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        return $plan;
    }

    public function index(Request $request)
    {
        $plan = $this->getMasterPlan($request);
        if (in_array($request->user()?->role, ['qa_qc', 'accountant'], true)) {
            // Put upcoming shipment dates first, in calendar order. Rows without
            // a Confirmed Date remain visible at the end of these grouped views.
            $plan = $plan->sortBy(fn ($item) => filled($item->Confirm_date)
                ? (string) $item->Confirm_date
                : '9999-12-31')->values();
        }

        return view('admin.masterplan.masterplan', compact('plan'));
    }

    public function confirmDate(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $request->merge(['month' => $month]);
        $month = $request->validate(['month' => 'required|date_format:Y-m'])['month'];

        $plan = $this->getMasterPlan($request)
            ->filter(function ($item) use ($month) {
                return filled($item->Confirm_date)
                    && substr((string) $item->Confirm_date, 0, 7) === $month;
            })
            ->sortBy(fn ($item) => (string) $item->Confirm_date)
            ->values();

        return view('admin.masterplan.confirm-date', compact('plan', 'month'));
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
            'Order Status',
            'MPS Status',
            'Priority',
            'Qty_dis',
            'Require_date',
            'Confirm_date',
            'Planned Cut Start',
            'Planned Cut End',
            'Planned Sew Start',
            'Planned Sew End',
            'Daily Target',
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
            'BOM Style',
            'Fabric Cost',
            'Trim Cost',
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
                $item->order_status ?? '',
                $item->mps_status ?? 'planned',
                $item->mps_priority ?? 'medium',
                $item->Qty_dis ?? '',
                $item->Require_date ?? '',
                $item->Confirm_date ?? '',
                $item->planned_cut_start ?? '',
                $item->planned_cut_end ?? '',
                $item->planned_sew_start ?? '',
                $item->planned_sew_end ?? '',
                $item->daily_target_qty ?? '',
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
                $item->bom_style ?? '',
                $item->total_fabric_cost ?? '',
                $item->total_trim_cost ?? '',
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
        $usedCus = DB::table('mtp')->pluck('CU')->unique();
        $ocs = DB::table('ocs')
            ->leftJoin('bom_headers', 'ocs.bom_header_id', '=', 'bom_headers.id')
            ->select('ocs.*', 'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version')
            ->whereNotIn('ocs.CS', $usedCus)
            ->orderBy('ocs.CS', 'asc')
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
            'Norm_date' => 'nullable|date',
            'fabric_issue_date' => 'nullable|date',
            'trims_issue_date' => 'nullable|date',
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
            'Confirm_date' => 'nullable|date',
            // MPS new fields
            'mps_status' => 'nullable|in:planned,in_production,completed,on_hold',
            'planned_cut_start' => 'nullable|date',
            'planned_cut_end' => 'nullable|date',
            'planned_sew_start' => 'nullable|date',
            'planned_sew_end' => 'nullable|date',
            'mps_priority' => 'nullable|in:low,medium,high,urgent',
            'daily_target_qty' => 'nullable|integer|min:0',
        ]);

        $ocs = DB::table('ocs')->where('CS', $request->CU)->first();

        if (!$ocs) {
            return back()->withErrors(['CU' => 'CS not found in OCS'])->withInput();
        }

        if (DB::table('mtp')->where('CU', $request->CU)->exists()) {
            return back()->withErrors(['CU' => 'This CS already exists in Master Plan.'])->withInput();
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
                'Norm_date' => $this->nullableDate($request->Norm_date),
                'fabric_issue_date' => $this->nullableDate($request->fabric_issue_date),
                'trims_issue_date' => $this->nullableDate($request->trims_issue_date),
                'inWHDate' => $this->nullableDate($request->inWHDate),
                '3rd_PartyInspection' => filled($request->input('3rd_PartyInspection')) ? $request->input('3rd_PartyInspection') : null,
                'qa_inspection_date' => $this->nullableDate($request->qa_inspection_date),
                'qa_status' => $request->qa_status ?? 'not_approved',
                'ShipDate2' => $this->nullableDate($request->ShipDate2),
                'SoTK' => filled($request->SoTK) ? $request->SoTK : null,
                'ExQty' => $this->nullableInteger($request->ExQty),
                'lt' => $this->nullableInteger($request->lt),
                'FirstOPT' => $this->nullableDate($request->FirstOPT),
                'Qty_dis' => $this->nullableInteger($request->Qty_dis),
                'Require_date' => $this->nullableDate($ocs->expected_ship_date),
                'Confirm_date' => $this->nullableDate($request->Confirm_date),
                // MPS new fields
                'mps_status' => $request->mps_status ?? 'planned',
                'planned_cut_start' => $this->nullableDate($request->planned_cut_start),
                'planned_cut_end' => $this->nullableDate($request->planned_cut_end),
                'planned_sew_start' => $this->nullableDate($request->planned_sew_start),
                'planned_sew_end' => $this->nullableDate($request->planned_sew_end),
                'mps_priority' => $request->mps_priority ?? 'medium',
                'daily_target_qty' => $this->nullableInteger($request->daily_target_qty),
                'mps_notes' => $request->mps_notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(RevenueMasterPlanSync::class)->syncReadyMasterPlans();

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
            ->leftJoin('bom_headers', 'ocs.bom_header_id', '=', 'bom_headers.id')
            ->select(
                'mtp.*',
                'ocs.SNo as Style',
                'ocs.ONum as PO',
                'ocs.Qty',
                'ocs.expected_ship_date',
                'ocs.status as order_status',
                'ocs.bom_header_id',
                'bom_headers.style_no as bom_style',
                'bom_headers.version as bom_version'
            )
            ->where('mtp.id', $id)
            ->first();

        $colors = DB::table('colors')
            ->select('name', 'hex_code')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $ocs = DB::table('ocs')
            ->leftJoin('bom_headers', 'ocs.bom_header_id', '=', 'bom_headers.id')
            ->select('ocs.*', 'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version')
            ->orderBy('ocs.CS', 'asc')
            ->get();

        $fabricOnly = false;
        $updateRoute = route('admin.masterplan.update', $id);

        return view('admin.masterplan.editmaster', compact('plan', 'fabricOnly', 'updateRoute', 'colors', 'ocs'));
    }

    public function editFabric(string $id)
    {
        $plan = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->select(
                'mtp.*',
                'ocs.SNo as Style',
                'ocs.ONum as PO',
                'ocs.Qty',
                'ocs.expected_ship_date'
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

    public function editBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'required|integer|distinct|exists:mtp,id',
        ]);

        $plans = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->select('mtp.*', 'ocs.SNo as Style')
            ->whereIn('mtp.id', $validated['ids'])
            ->orderBy('Line')->orderBy('CU')->get();

        return view('admin.masterplan.bulk-edit', compact('plans'));
    }

    public function updateBulk(Request $request)
    {
        $fields = [
            'Require_date', 'Confirm_date', 'planned_cut_start', 'planned_cut_end',
            'planned_sew_start', 'planned_sew_end', 'Norm_date', 'fabric_issue_date', 'trims_issue_date', 'inWHDate',
            '3rd_PartyInspection', 'ShipDate2', 'SoTK', 'ExQty', 'lt', 'FirstOPT',
        ];
        $rules = [
            'rows' => 'required|array|min:1|max:500',
            'rows.*.id' => 'required|integer|distinct|exists:mtp,id',
            'rows.*.Require_date' => 'nullable|date',
            'rows.*.Confirm_date' => 'nullable|date',
            'rows.*.planned_cut_start' => 'nullable|date',
            'rows.*.planned_cut_end' => 'nullable|date',
            'rows.*.planned_sew_start' => 'nullable|date',
            'rows.*.planned_sew_end' => 'nullable|date',
            'rows.*.Norm_date' => 'nullable|date',
            'rows.*.fabric_issue_date' => 'nullable|date',
            'rows.*.trims_issue_date' => 'nullable|date',
            'rows.*.inWHDate' => 'nullable|date',
            'rows.*.3rd_PartyInspection' => 'nullable|string|max:50',
            'rows.*.ShipDate2' => 'nullable|date',
            'rows.*.SoTK' => 'nullable|string|max:50',
            'rows.*.ExQty' => 'nullable|integer|min:0',
            'rows.*.lt' => 'nullable|integer|min:0',
            'rows.*.FirstOPT' => ['nullable', 'date', function ($attribute, $value, $fail) {
                if ($value && Carbon::parse($value)->isSunday()) {
                    $fail('FirstOPT cannot be on a Sunday. Please choose another date.');
                }
            }],
        ];
        $validated = $request->validate($rules);

        foreach ($validated['rows'] as $index => $row) {
            if (filled($row['ExQty'] ?? null)) {
                $qtyDis = DB::table('mtp')->where('id', $row['id'])->value('Qty_dis');
                if ($qtyDis !== null && (int) $row['ExQty'] > (int) $qtyDis) {
                    return back()->withErrors([
                        "rows.{$index}.ExQty" => 'ExQty cannot be greater than Qty_dis for this CU.',
                    ])->withInput();
                }
            }
        }

        DB::transaction(function () use ($validated, $fields) {
            foreach ($validated['rows'] as $row) {
                $updates = ['updated_at' => now()];
                foreach ($fields as $field) {
                    $value = $row[$field] ?? null;
                    $updates[$field] = in_array($field, ['ExQty', 'lt'], true)
                        ? $this->nullableInteger($value)
                        : ($field === '3rd_PartyInspection' || $field === 'SoTK'
                            ? (filled($value) ? $value : null)
                            : $this->nullableDate($value));
                }
                DB::table('mtp')->where('id', $row['id'])->update($updates);
            }
        });

        app(RevenueMasterPlanSync::class)->syncReadyMasterPlans();

        return redirect()->route('admin.masterplan.index')->with('success', 'Selected Master Plan rows updated successfully.');
    }

    public function editWarehouse(string $id)
    {
        $plan = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->select('mtp.*', 'ocs.SNo as Style', 'ocs.ONum as PO', 'ocs.Qty as Order_Qty')
            ->where('mtp.id', $id)
            ->first();

        abort_unless($plan, 404);

        return view('admin.masterplan.warehouse-edit', compact('plan'));
    }

    public function updateWarehouse(Request $request, string $id)
    {
        $data = $request->validate([
            'Norm_date' => 'nullable|date',
            'fabric_issue_date' => 'nullable|date',
            'trims_issue_date' => 'nullable|date',
            'mps_notes' => 'nullable|string|max:5000',
        ]);

        $updated = DB::table('mtp')->where('id', $id)->update([
            'Norm_date' => $this->nullableDate($data['Norm_date'] ?? null),
            'fabric_issue_date' => $this->nullableDate($data['fabric_issue_date'] ?? null),
            'trims_issue_date' => $this->nullableDate($data['trims_issue_date'] ?? null),
            'mps_notes' => $data['mps_notes'] ?? null,
            'updated_at' => now(),
        ]);

        abort_unless($updated || DB::table('mtp')->where('id', $id)->exists(), 404);

        return redirect()->route('masterplan.view', $request->query())
            ->with('success', 'Warehouse planning updated.');
    }

    public function updateAccountantNote(Request $request, string $id)
    {
        $data = $request->validate([
            'mps_notes' => 'nullable|string|max:5000',
        ]);

        $updated = DB::table('mtp')->where('id', $id)->update([
            'mps_notes' => $data['mps_notes'] ?? null,
            'updated_at' => now(),
        ]);

        abort_unless($updated || DB::table('mtp')->where('id', $id)->exists(), 404);

        return redirect()->route('masterplan.view', $request->query())
            ->with('success', 'Note updated successfully.');
    }

    public function updateQaQc(Request $request, string $id)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'qa_qc'], true), 403);

        $data = $request->validate([
            '3rd_PartyInspection' => 'nullable|string|max:50',
            'qa_inspection_date' => 'nullable|date',
            'qa_status' => 'required|in:approved,not_approved',
        ]);

        $updated = DB::table('mtp')->where('id', $id)->update([
            '3rd_PartyInspection' => filled($data['3rd_PartyInspection'] ?? null) ? $data['3rd_PartyInspection'] : null,
            'qa_inspection_date' => $this->nullableDate($data['qa_inspection_date'] ?? null),
            'qa_status' => $data['qa_status'],
            'updated_at' => now(),
        ]);

        abort_unless($updated || DB::table('mtp')->where('id', $id)->exists(), 404);

        return redirect()->route('masterplan.view')->with('success', 'QA/QC inspection updated.');
    }

    public function editQaQc(string $id)
    {
        $plan = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->select(
                'mtp.id', 'mtp.CU', 'mtp.Line', 'mtp.Qty_dis', 'mtp.Require_date', 'mtp.Confirm_date',
                'mtp.inWHDate', 'mtp.3rd_PartyInspection', 'mtp.qa_inspection_date', 'mtp.qa_status',
                'ocs.SNo as Style', 'ocs.ONum as PO', 'ocs.Qty as Order_Qty'
            )
            ->where('mtp.id', $id)
            ->first();

        abort_unless($plan, 404);

        return view('admin.masterplan.qa-qc-edit', compact('plan'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'CU' => 'required',
            'Line' => 'required',
            'LineColor' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}){1,2}$/'],
            'Norm_date' => 'nullable|date',
            'fabric_issue_date' => 'nullable|date',
            'trims_issue_date' => 'nullable|date',
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
            'qa_inspection_date' => 'nullable|date',
            'qa_status' => 'nullable|in:approved,not_approved',
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
            'Confirm_date' => 'nullable|date',
            // MPS new fields
            'mps_status' => 'nullable|in:planned,in_production,completed,on_hold',
            'planned_cut_start' => 'nullable|date',
            'planned_cut_end' => 'nullable|date',
            'planned_sew_start' => 'nullable|date',
            'planned_sew_end' => 'nullable|date',
            'mps_priority' => 'nullable|in:low,medium,high,urgent',
            'daily_target_qty' => 'nullable|integer|min:0',
        ], [
            'CU.unique' => 'CU already exists!',
        ]);

        $ocs = DB::table('ocs')->where('CS', $request->CU)->first();

        if (!$ocs) {
            return back()->withErrors(['CU' => 'CS not found in OCS'])->withInput();
        }

        if (DB::table('mtp')->where('CU', $request->CU)->where('id', '!=', $id)->exists()) {
            return back()->withErrors(['CU' => 'This CS already exists in another Master Plan record.'])->withInput();
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
                'Norm_date' => $this->nullableDate($request->Norm_date),
                'fabric_issue_date' => $this->nullableDate($request->fabric_issue_date),
                'trims_issue_date' => $this->nullableDate($request->trims_issue_date),
                'inWHDate' => $this->nullableDate($request->inWHDate),
                '3rd_PartyInspection' => filled($request->input('3rd_PartyInspection')) ? $request->input('3rd_PartyInspection') : null,
                'qa_inspection_date' => $this->nullableDate($request->qa_inspection_date),
                'qa_status' => $request->qa_status ?? 'not_approved',
                'ShipDate2' => $this->nullableDate($request->ShipDate2),
                'SoTK' => filled($request->SoTK) ? $request->SoTK : null,
                'ExQty' => $this->nullableInteger($request->ExQty),
                'lt' => $this->nullableInteger($request->lt),
                'FirstOPT' => $this->nullableDate($request->FirstOPT),
                'Qty_dis' => $this->nullableInteger($request->Qty_dis),
                'Require_date' => $this->nullableDate($ocs->expected_ship_date),
                'Confirm_date' => $this->nullableDate($request->Confirm_date),
                'mps_status' => $request->mps_status ?? 'planned',
                'planned_cut_start' => $this->nullableDate($request->planned_cut_start),
                'planned_cut_end' => $this->nullableDate($request->planned_cut_end),
                'planned_sew_start' => $this->nullableDate($request->planned_sew_start),
                'planned_sew_end' => $this->nullableDate($request->planned_sew_end),
                'mps_priority' => $request->mps_priority ?? 'medium',
                'daily_target_qty' => $this->nullableInteger($request->daily_target_qty),
                'mps_notes' => $request->mps_notes,
                'updated_at' => now(),
            ]);

            app(RevenueMasterPlanSync::class)->syncReadyMasterPlans();

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
