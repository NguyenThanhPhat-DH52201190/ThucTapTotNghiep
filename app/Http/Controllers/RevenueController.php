<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RevenueController extends Controller
{
    private function distributionColumn(): string
    {
        return 'Qty_dis';
    }

    private function getDistributionByLineSubquery()
    {
        $distributionColumn = $this->distributionColumn();

        return DB::table('mtp')
            ->select('CU', 'Line', DB::raw('SUM(COALESCE(' . $distributionColumn . ', 0)) as Distribution'))
            ->groupBy('CU', 'Line');
    }

    private function getLineMetaSubquery()
    {
        return DB::table('mtp')
            ->select(
                'CU',
                'Line',
                DB::raw('MAX(COALESCE(LineColor, "#808080")) as LineColor'),
                DB::raw('MIN(FirstOPT) as FirstOPT'),
                DB::raw('MIN(lt) as lt')
            )
            ->groupBy('CU', 'Line');
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
        $cursor = $includeStart ? $start->copy() : $start->copy()->addDay();
        $count = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($cursor->isSunday() || isset($holidaySet[$cursor->toDateString()])) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    private function skipSunday(Carbon $date): Carbon
    {
        $result = $date->copy();

        if ($result->isSunday()) {
            $result->addDay();
        }

        return $result;
    }

    private function calcFinishSew($startDate, $days, array $holidays = [])
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
                return $this->skipSunday($end);
            }

            $totalDays = $newTotalDays;
        }
    }

    private function calcExFact($startDate, $days, array $holidays = [])
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

    private function getMasterPlanWindowsByLine(string $line, array $holidays): Collection
    {
        $masterPlans = DB::table('mtp')
            ->where('Line', $line)
            ->select('id', 'CU', 'FirstOPT', 'lt')
            ->orderByRaw('FirstOPT IS NULL ASC')
            ->orderBy('FirstOPT')
            ->orderBy('id')
            ->get();

        $windows = collect();
        $previousFinish = null;

        foreach ($masterPlans as $masterPlan) {
            if (!$previousFinish) {
                $currentFirstOPT = $masterPlan->FirstOPT ? Carbon::parse($masterPlan->FirstOPT) : null;
            } else {
                $currentFirstOPT = $this->calcExFact($previousFinish, 1, $holidays);
            }

            if (!$currentFirstOPT || !$masterPlan->lt) {
                continue;
            }

            $currentFinish = $this->calcFinishSew($currentFirstOPT, (int) $masterPlan->lt, $holidays);

            $windows->push((object) [
                'CU' => (string) $masterPlan->CU,
                'firstOPT' => $currentFirstOPT,
                'finishSEW' => $currentFinish,
            ]);
            $previousFinish = $currentFinish;
        }

        return $windows;
    }

    private function attachMasterPlanWindows(Collection $revenues, array $holidays): Collection
    {
        $groups = $revenues->groupBy(function ($item) {
            return (string) $item->SewingLine;
        });

        foreach ($groups as $group) {
            $orderedRevenues = $group->sortBy('id')->values();
            $line = (string) $orderedRevenues->first()->SewingLine;
            $lineWindows = $this->getMasterPlanWindowsByLine($line, $holidays);
            $windowsByCu = $lineWindows->groupBy(function ($window) {
                return (string) $window->CU;
            })->map(function ($items) {
                return $items->values();
            });

            $cuIndexes = [];

            foreach ($orderedRevenues as $item) {
                $cu = (string) $item->CS;
                $currentIndex = $cuIndexes[$cu] ?? 0;
                $window = $windowsByCu->get($cu)?->get($currentIndex);

                $item->calc_FirstOPT = $window->firstOPT ?? null;
                $item->calc_Finish_SEW = $window->finishSEW ?? null;

                $cuIndexes[$cu] = $currentIndex + 1;
            }
        }

        return $revenues;
    }

    private function sortByFirstOPT(Collection $revenues, bool $preserveKeys = false): Collection
    {
        $sorted = $revenues->sort(function ($left, $right) {
            $leftFirstOPT = $left->calc_FirstOPT ?? null;
            $rightFirstOPT = $right->calc_FirstOPT ?? null;

            $leftTimestamp = $leftFirstOPT ? $leftFirstOPT->timestamp : PHP_INT_MAX;
            $rightTimestamp = $rightFirstOPT ? $rightFirstOPT->timestamp : PHP_INT_MAX;

            if ($leftTimestamp !== $rightTimestamp) {
                return $leftTimestamp <=> $rightTimestamp;
            }

            return ((int) ($left->id ?? 0)) <=> ((int) ($right->id ?? 0));
        })->values();

        if ($preserveKeys) {
            return $sorted->keyBy('id');
        }

        return $sorted;
    }

    private function getMonthlyActualOutSubquery(string $month)
    {
        return DB::table('daily_revenues')
            ->select('revenue_id', DB::raw('SUM(COALESCE(qty, 0)) as monthly_actualout'))
            ->whereRaw("DATE_FORMAT(work_date, '%Y-%m') = ?", [$month])
            ->groupBy('revenue_id');
    }

    private function monthDays(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $daysInMonth = $start->daysInMonth;

        return range(1, $daysInMonth);
    }

    private function lineOrderCase(string $column = 'revenue.SewingLine'): string
    {
        return "CASE
            WHEN LOWER(TRIM({$column})) = 'green' THEN 1
            WHEN LOWER(TRIM({$column})) = 'blue' THEN 2
            WHEN LOWER(TRIM({$column})) = 'orange' THEN 3
            WHEN LOWER(TRIM({$column})) = 'yellow' THEN 4
            ELSE 5
        END";
    }

    private function getRevenues(Request $request): Collection
    {
        $distributionByLine = $this->getDistributionByLineSubquery();
        $lineMeta = $this->getLineMetaSubquery();
        $month = $request->input('month', now()->format('Y-m'));
        $monthlyActualOut = $this->getMonthlyActualOutSubquery($month);

        return DB::table('revenue')
            ->join('ocs', 'revenue.CS', '=', 'ocs.CS')
            ->leftJoinSub($distributionByLine, 'mtp_dist', function ($join) {
                $join->on('mtp_dist.CU', '=', 'revenue.CS')
                    ->on('mtp_dist.Line', '=', 'revenue.SewingLine');
            })
            ->leftJoinSub($lineMeta, 'mtp_meta', function ($join) {
                $join->on('mtp_meta.CU', '=', 'revenue.CS')
                    ->on('mtp_meta.Line', '=', 'revenue.SewingLine');
            })
            ->leftJoinSub($monthlyActualOut, 'daily_monthly', function ($join) {
                $join->on('daily_monthly.revenue_id', '=', 'revenue.id');
            })
            ->when($request->filled('cs'), function ($query) use ($request) {
                $query->where('revenue.CS', 'like', '%' . $request->cs . '%');
            })
            ->select(
                'revenue.id',
                'revenue.CS',
                'revenue.SewingLine',
                'revenue.planout',
                'revenue.sewingmp',
                'revenue.workhrs',
                'ocs.CMT as cmp',
                DB::raw('COALESCE(mtp_dist.Distribution, 0) as Distribution'),
                DB::raw('COALESCE(mtp_meta.LineColor, "#808080") as LineColor'),
                DB::raw('COALESCE(daily_monthly.monthly_actualout, 0) as actualout'),
                DB::raw('mtp_meta.FirstOPT as FirstOPT'),
                DB::raw('mtp_meta.lt as lt')
            )
            ->orderByRaw($this->lineOrderCase('revenue.SewingLine'))
            ->orderByRaw("CASE
                WHEN LOWER(TRIM(revenue.SewingLine)) IN ('green', 'blue', 'orange', 'yellow') THEN 0
                ELSE 1
            END")
            ->orderBy('revenue.SewingLine')
            ->orderByRaw('mtp_meta.FirstOPT IS NULL ASC')
            ->orderBy('mtp_meta.FirstOPT')
            ->orderBy('revenue.CS')
            ->get();
    }

    // List view
    public function index(Request $request)
    {
        $revenues = $this->getRevenues($request);

        return view('admin.revenue.revenue', compact('revenues'));
    }

    public function export(Request $request)
    {
        $revenues = $this->getRevenues($request);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Revenue');

        $headers = ['CS', 'SewingLine', 'Distribution', 'planout', 'actualout', 'sewingmp', 'workhrs', 'cmp'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $header);
        }

        $rowIndex = 2;
        foreach ($revenues as $item) {
            $sheet->setCellValue('A' . $rowIndex, $item->CS ?? '');
            $sheet->setCellValue('B' . $rowIndex, $item->SewingLine ?? '');
            $sheet->setCellValue('C' . $rowIndex, $item->Distribution ?? 0);
            $sheet->setCellValue('D' . $rowIndex, $item->planout ?? '');
            $sheet->setCellValue('E' . $rowIndex, $item->actualout ?? '');
            $sheet->setCellValue('F' . $rowIndex, $item->sewingmp ?? '');
            $sheet->setCellValue('G' . $rowIndex, $item->workhrs ?? '');
            $sheet->setCellValue('H' . $rowIndex, $item->cmp ?? '');
            $rowIndex++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'revenue-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // Create form
    public function create()
    {
        $ocs = DB::table('ocs')
            ->select('CS')
            ->orderBy('CS')
            ->get();

        return view('admin.revenue.addrevenue', compact('ocs'));
    }

    // Store
    public function store(Request $request)
    {
        $request->validate([
            'CS' => 'required',
            'SewingLine' => 'required',
            'planout' => 'required|numeric',
            'sewingmp' => 'required|numeric',
            'workhrs' => 'required|numeric',
        ]);

        try {
            DB::table('revenue')->insert([
                'CS' => $request->CS,
                'SewingLine' => $request->SewingLine,
                'planout' => $request->planout,
                'actualout' => 0,
                'sewingmp' => $request->sewingmp,
                'workhrs' => $request->workhrs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()
                ->route('admin.revenue.index')
                ->with('success', 'Added successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to create revenue record', [
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
        $distributionByLine = $this->getDistributionByLineSubquery();
        $lineMeta = $this->getLineMetaSubquery();
        $month = now()->format('Y-m');
        $monthlyActualOut = $this->getMonthlyActualOutSubquery($month);

        $revenue = DB::table('revenue')
        ->leftJoin('ocs', 'revenue.CS', '=', 'ocs.CS')
        ->leftJoinSub($distributionByLine, 'mtp_dist', function ($join) {
            $join->on('mtp_dist.CU', '=', 'revenue.CS')
                ->on('mtp_dist.Line', '=', 'revenue.SewingLine');
        })
        ->leftJoinSub($lineMeta, 'mtp_meta', function ($join) {
            $join->on('mtp_meta.CU', '=', 'revenue.CS')
                ->on('mtp_meta.Line', '=', 'revenue.SewingLine');
        })
        ->leftJoinSub($monthlyActualOut, 'daily_monthly', function ($join) {
            $join->on('daily_monthly.revenue_id', '=', 'revenue.id');
        })
        ->select(
            'revenue.*',
            'ocs.CMT as cmp',
            DB::raw('COALESCE(mtp_dist.Distribution, 0) as Distribution'),
            DB::raw('COALESCE(mtp_meta.LineColor, "#808080") as LineColor'),
            DB::raw('COALESCE(daily_monthly.monthly_actualout, 0) as actualout')
        )
        ->where('revenue.id', $id)
        ->first();

        if (!$revenue) {
            return redirect()->route('admin.revenue.index')
                ->with('error', 'Record not found.');
        }

        return view('admin.revenue.editrevenue', compact('revenue'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'planout' => 'required|numeric',
            'sewingmp' => 'required|numeric',
            'workhrs' => 'required|numeric',
        ]);

        try {
            DB::table('revenue')->where('id', $id)->update([
                'planout' => $request->planout,
                'sewingmp' => $request->sewingmp,
                'workhrs' => $request->workhrs,
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.revenue.index')
                ->with('success', 'Updated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to update revenue record', [
                'message' => $e->getMessage(),
                'id' => $id,
                'input' => $request->except(['_token', '_method']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update the record. Please check your input and try again.');
        }
    }

    // Delete
    public function destroy($id)
    {
        try {
            $revenue = DB::table('revenue')->where('id', $id)->first();

            if (!$revenue) {
                return redirect()->back()->with('error', 'Record not found.');
            }

            DB::table('revenue')->where('id', $id)->delete();

            return redirect()->route('admin.revenue.index')
                ->with('success', 'Deleted successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to delete revenue record', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return redirect()->back()
                ->with('error', 'Unable to delete the record. Please try again.');
        }
    }

    public function getSewingLinesByCs(string $cs)
    {
        $lines = DB::table('mtp')
            ->where('CU', $cs)
            ->whereNotNull('Line')
            ->where('Line', '!=', '')
            ->orderBy('Line')
            ->pluck('Line')
            ->unique()
            ->values();

        return response()->json($lines);
    }

    public function getDistributionByCsAndLine(Request $request)
    {
        $distributionColumn = $this->distributionColumn();

        $request->validate([
            'cs' => 'required|string',
            'line' => 'required|string',
        ]);

        $distribution = DB::table('mtp')
            ->where('CU', $request->cs)
            ->where('Line', $request->line)
            ->sum(DB::raw('COALESCE(' . $distributionColumn . ', 0)'));

        return response()->json([
            'distribution' => (int) $distribution,
        ]);
    }

    public function dailyRevenue(Request $request)
    {
        $request->validate([
            'line' => 'required|string',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $line = $request->line;
        $month = $request->input('month', now()->format('Y-m'));
        $distributionByLine = $this->getDistributionByLineSubquery();
        $lineMeta = $this->getLineMetaSubquery();
        $monthlyActualOut = $this->getMonthlyActualOutSubquery($month);

        $revenues = DB::table('revenue')
            ->join('ocs', 'revenue.CS', '=', 'ocs.CS')
            ->leftJoinSub($distributionByLine, 'mtp_dist', function ($join) {
                $join->on('mtp_dist.CU', '=', 'revenue.CS')
                    ->on('mtp_dist.Line', '=', 'revenue.SewingLine');
            })
            ->leftJoinSub($lineMeta, 'mtp_meta', function ($join) {
                $join->on('mtp_meta.CU', '=', 'revenue.CS')
                    ->on('mtp_meta.Line', '=', 'revenue.SewingLine');
            })
            ->leftJoinSub($monthlyActualOut, 'daily_monthly', function ($join) {
                $join->on('daily_monthly.revenue_id', '=', 'revenue.id');
            })
            ->where('revenue.SewingLine', $line)
            ->select(
                'revenue.id',
                'revenue.CS',
                'revenue.SewingLine',
                'revenue.planout',
                'ocs.CMT as cmp',
                DB::raw('COALESCE(mtp_dist.Distribution, 0) as Distribution'),
                DB::raw('COALESCE(mtp_meta.LineColor, "#808080") as LineColor'),
                DB::raw('COALESCE(daily_monthly.monthly_actualout, 0) as actualout')
            )
            ->orderBy('revenue.CS')
            ->orderBy('revenue.id')
            ->get();

        $holidays = DB::table('holidays')->pluck('holiday')->toArray();
        $holidaySet = $this->normalizeHolidaySet($holidays);
        $revenues = $this->attachMasterPlanWindows($revenues, $holidays);
        $revenues = $this->sortByFirstOPT($revenues);

        $monthLabel = Carbon::createFromFormat('Y-m', $month)->format('M');
        $days = $this->monthDays($month);

        $dailyMatrixRows = DB::table('daily_revenues')
            ->whereIn('revenue_id', $revenues->pluck('id'))
            ->whereRaw("DATE_FORMAT(work_date, '%Y-%m') = ?", [$month])
            ->select('revenue_id', DB::raw('DAY(work_date) as day_number'), 'qty')
            ->get();

        $dailyMatrix = [];
        foreach ($dailyMatrixRows as $row) {
            $dailyMatrix[(int) $row->revenue_id][(int) $row->day_number] = (int) $row->qty;
        }

        $totalQty = $revenues->sum('actualout');
        $totalPlanRevenue = $revenues->sum(function ($item) {
            return ((float) $item->planout) * ((float) $item->cmp);
        });
        $totalAmount = $revenues->sum(function ($item) {
            return ((float) $item->actualout) * ((float) $item->cmp);
        });

        return view('admin.revenue.daily_revenue', compact('line', 'month', 'monthLabel', 'revenues', 'days', 'dailyMatrix', 'holidaySet', 'totalQty', 'totalPlanRevenue', 'totalAmount'));
    }

    public function monthlyReport(Request $request)
    {
        $request->validate([
            'year' => 'nullable|digits:4',
        ]);

        $year = (int) $request->input('year', now()->year);

        $monthlyActualByRevenue = DB::table('daily_revenues as dr')
            ->whereYear('dr.work_date', $year)
            ->select(
                'dr.revenue_id',
                DB::raw('MONTH(dr.work_date) as month_no'),
                DB::raw('SUM(COALESCE(dr.qty, 0)) as monthly_qty')
            )
            ->groupBy('dr.revenue_id', DB::raw('MONTH(dr.work_date)'));

        $colorLineCase = "CASE WHEN LOWER(TRIM(r.SewingLine)) IN ('green', 'blue', 'orange', 'yellow') THEN 1 ELSE 0 END";
        $monthBucket = 'COALESCE(dm.month_no, MONTH(r.created_at))';

        $rows = DB::query()
            ->from('revenue as r')
            ->join('ocs', 'ocs.CS', '=', 'r.CS')
            ->leftJoinSub($monthlyActualByRevenue, 'dm', function ($join) {
                $join->on('dm.revenue_id', '=', 'r.id');
            })
            ->whereRaw('(YEAR(r.created_at) = ? OR dm.revenue_id IS NOT NULL)', [$year])
            ->select(
                DB::raw($monthBucket . ' as month_no'),
                DB::raw('SUM(CASE WHEN ' . $colorLineCase . ' = 1 THEN COALESCE(r.planout, 0) * COALESCE(ocs.CMT, 0) ELSE 0 END) as gsv_plan'),
                DB::raw('SUM(CASE WHEN ' . $colorLineCase . ' = 1 THEN COALESCE(dm.monthly_qty, 0) * COALESCE(ocs.CMT, 0) ELSE 0 END) as gsv_actual'),
                DB::raw('SUM(CASE WHEN ' . $colorLineCase . ' = 0 THEN COALESCE(r.planout, 0) * COALESCE(ocs.CMT, 0) ELSE 0 END) as subcon_plan'),
                DB::raw('SUM(CASE WHEN ' . $colorLineCase . ' = 0 THEN COALESCE(dm.monthly_qty, 0) * COALESCE(ocs.CMT, 0) ELSE 0 END) as subcon_actual')
            )
            ->groupByRaw($monthBucket)
            ->orderByRaw($monthBucket)
            ->get();

        $monthLabels = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthLabels[] = Carbon::create($year, $m, 1)->format('M-y');
        }

        $gsvPlanData = [];
        $gsvActualData = [];
        $subconPlanData = [];
        $subconActualData = [];
        $tableRows = [];

        for ($m = 1; $m <= 12; $m++) {
            $monthRow = $rows->firstWhere('month_no', $m);

            $gsvPlan = (float) ($monthRow->gsv_plan ?? 0);
            $gsvActual = (float) ($monthRow->gsv_actual ?? 0);
            $subconPlan = (float) ($monthRow->subcon_plan ?? 0);
            $subconActual = (float) ($monthRow->subcon_actual ?? 0);

            $gsvPlanData[] = $gsvPlan;
            $gsvActualData[] = $gsvActual;
            $subconPlanData[] = $subconPlan;
            $subconActualData[] = $subconActual;

            $tableRows[] = [
                'monthNo' => $m,
                'monthLabel' => Carbon::create($year, $m, 1)->format('M-y'),
                'gsvPlan' => $gsvPlan,
                'gsvActual' => $gsvActual,
                'subconPlan' => $subconPlan,
                'subconActual' => $subconActual,
            ];
        }

        $datasets = [
            [
                'label' => 'GSVPlan',
                'backgroundColor' => '#d97706',
                'data' => $gsvPlanData,
            ],
            [
                'label' => 'GSVActual',
                'backgroundColor' => '#7dd3fc',
                'data' => $gsvActualData,
            ],
            [
                'label' => 'SubconPlan',
                'backgroundColor' => '#22c55e',
                'data' => $subconPlanData,
            ],
            [
                'label' => 'SubconActual',
                'backgroundColor' => '#fde68a',
                'data' => $subconActualData,
            ],
        ];

        $totals = [
            'gsvPlan' => array_sum($gsvPlanData),
            'gsvActual' => array_sum($gsvActualData),
            'subconPlan' => array_sum($subconPlanData),
            'subconActual' => array_sum($subconActualData),
        ];

        return view('admin.revenue.monthly_report', [
            'year' => $year,
            'monthLabels' => $monthLabels,
            'datasets' => $datasets,
            'tableRows' => $tableRows,
            'totals' => $totals,
        ]);
    }

    public function storeDailyRevenue(Request $request)
    {
        $request->validate([
            'line' => 'required|string',
            'month' => 'required|date_format:Y-m',
            'revenue_id' => 'required|integer',
            'work_date' => 'required|date',
            'qty' => 'required|integer|min:0',
        ]);

        $revenue = DB::table('revenue')->where('id', $request->revenue_id)->first();

        if (!$revenue) {
            return back()->with('error', 'Revenue record not found.');
        }

        if ((string) $revenue->SewingLine !== (string) $request->line) {
            return back()->with('error', 'Invalid line for selected revenue row.');
        }

        $distribution = (int) DB::table('mtp')
            ->where('CU', $revenue->CS)
            ->where('Line', $revenue->SewingLine)
            ->sum(DB::raw('COALESCE(' . $this->distributionColumn() . ', 0)'));

        $entryMonth = date('Y-m', strtotime((string) $request->work_date));
        $currentTotal = (int) DB::table('daily_revenues')
            ->where('revenue_id', $revenue->id)
            ->whereRaw("DATE_FORMAT(work_date, '%Y-%m') = ?", [$entryMonth])
            ->sum('qty');

        $currentDayQty = (int) DB::table('daily_revenues')
            ->where('revenue_id', $revenue->id)
            ->whereDate('work_date', $request->work_date)
            ->value('qty');

        $newTotal = $currentTotal - $currentDayQty + (int) $request->qty;

        if ($newTotal > $distribution) {
            return back()->withInput()->with('error', 'Total monthly quantity (' . $newTotal . ') exceeds Distribution (' . $distribution . ').');
        }

        $existingDaily = DB::table('daily_revenues')
            ->where('revenue_id', $revenue->id)
            ->whereDate('work_date', $request->work_date)
            ->first();

        if ($existingDaily) {
            DB::table('daily_revenues')
                ->where('id', $existingDaily->id)
                ->update([
                    'qty' => (int) $request->qty,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('daily_revenues')->insert([
                'revenue_id' => $revenue->id,
                'work_date' => $request->work_date,
                'qty' => (int) $request->qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($entryMonth === now()->format('Y-m')) {
            DB::table('revenue')->where('id', $revenue->id)->update([
                'actualout' => $newTotal,
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('revenue.daily.line', [
            'line' => $request->line,
            'month' => $request->month,
        ])->with('success', 'Daily quantity saved successfully.');
    }

    public function storeDailyRevenueMatrix(Request $request)
    {
        $request->validate([
            'line' => 'required|string',
            'month' => 'required|date_format:Y-m',
            'matrix' => 'nullable|array',
        ]);

        $line = (string) $request->line;
        $month = (string) $request->month;
        $days = $this->monthDays($month);
        $matrix = (array) $request->input('matrix', []);

        $distributionByLine = $this->getDistributionByLineSubquery();

        $revenues = DB::table('revenue')
            ->leftJoinSub($distributionByLine, 'mtp_dist', function ($join) {
                $join->on('mtp_dist.CU', '=', 'revenue.CS')
                    ->on('mtp_dist.Line', '=', 'revenue.SewingLine');
            })
            ->where('revenue.SewingLine', $line)
            ->select(
                'revenue.id',
                'revenue.CS',
                'revenue.SewingLine',
                DB::raw('COALESCE(mtp_dist.Distribution, 0) as Distribution')
            )
            ->orderBy('revenue.CS')
            ->orderBy('revenue.id')
            ->get()
            ->keyBy('id');

        if ($revenues->isEmpty()) {
            return back()->with('error', 'No revenue rows found for this line.');
        }

        $prepared = [];

        foreach ($revenues as $revenueId => $revenue) {
            $rowInput = (array) ($matrix[$revenueId] ?? []);
            $rowPrepared = [];
            $rowTotal = 0;

            foreach ($days as $day) {
                $raw = $rowInput[$day] ?? null;

                if ($raw === null || $raw === '') {
                    $qty = 0;
                } elseif (filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw < 0) {
                    return back()
                        ->withInput()
                        ->with('error', 'Invalid quantity for CS ' . $revenue->CS . ' on day ' . $day . '. Please use non-negative integers.');
                } else {
                    $qty = (int) $raw;
                }

                $rowPrepared[$day] = $qty;
                $rowTotal += $qty;
            }

            if ($rowTotal > (int) $revenue->Distribution) {
                return back()
                    ->withInput()
                    ->with('error', 'Total monthly quantity for CS ' . $revenue->CS . ' (' . $rowTotal . ') exceeds Distribution (' . (int) $revenue->Distribution . ').');
            }

            $prepared[$revenueId] = [
                'total' => $rowTotal,
                'days' => $rowPrepared,
            ];
        }

        $existingRows = DB::table('daily_revenues')
            ->whereIn('revenue_id', $revenues->keys())
            ->whereRaw("DATE_FORMAT(work_date, '%Y-%m') = ?", [$month])
            ->select('id', 'revenue_id', DB::raw('DAY(work_date) as day_number'))
            ->get();

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[(int) $row->revenue_id][(int) $row->day_number] = (int) $row->id;
        }

        DB::transaction(function () use ($revenues, $prepared, $days, $month, $existingMap) {
            foreach ($revenues as $revenueId => $revenue) {
                foreach ($days as $day) {
                    $qty = $prepared[$revenueId]['days'][$day];
                    $workDate = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                    $existingId = $existingMap[(int) $revenueId][$day] ?? null;

                    if ($qty > 0) {
                        if ($existingId) {
                            DB::table('daily_revenues')
                                ->where('id', $existingId)
                                ->update([
                                    'qty' => $qty,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            DB::table('daily_revenues')->insert([
                                'revenue_id' => $revenueId,
                                'work_date' => $workDate,
                                'qty' => $qty,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    } elseif ($existingId) {
                        DB::table('daily_revenues')->where('id', $existingId)->delete();
                    }
                }

                if ($month === now()->format('Y-m')) {
                    DB::table('revenue')
                        ->where('id', $revenueId)
                        ->update([
                            'actualout' => $prepared[$revenueId]['total'],
                            'updated_at' => now(),
                        ]);
                }
            }
        }, 3);

        return redirect()->route('revenue.daily.line', [
            'line' => $line,
            'month' => $month,
        ])->with('success', 'Daily matrix saved successfully.');
    }
}
