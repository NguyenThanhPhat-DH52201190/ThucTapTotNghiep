<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FinanceController extends Controller
{
    public function fobCosts(Request $request)
    {
        $orders = DB::table('ocs')->where('order_type', 'fob')
            ->select('id', 'CS', 'SNo', 'Sname', 'Customer', 'Qty', 'unit_price', 'material_ownership')
            ->orderByDesc('id')->get();
        $components = DB::table('order_cost_components')
            ->join('ocs', 'order_cost_components.cutsheet_id', '=', 'ocs.id')
            ->select('order_cost_components.*', 'ocs.CS', 'ocs.SNo')
            ->orderByDesc('order_cost_components.id')->paginate(30);

        return view('admin.finance.fob-costs', compact('orders', 'components'));
    }

    public function storeFobCost(Request $request)
    {
        $data = $request->validate([
            'cutsheet_id' => 'required|exists:ocs,id',
            'cost_type' => 'required|in:freight,qc,packing,overhead,other',
            'cost_stage' => 'required|in:estimated,actual',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);
        if (!DB::table('ocs')->where('id', $data['cutsheet_id'])->where('order_type', 'fob')->exists()) {
            return back()->withInput()->with('error', 'FOB cost components can only be recorded for FOB orders.');
        }
        DB::table('order_cost_components')->insert($data + [
            'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'FOB cost component recorded. It will be included when the order costing snapshot is closed.');
    }

    public function deleteFobCost($id)
    {
        DB::table('order_cost_components')->where('id', $id)->delete();
        return back()->with('success', 'FOB cost component deleted.');
    }

    public function orderCostings(Request $request)
    {
        $costings = DB::table('order_costings')->join('ocs', 'order_costings.cutsheet_id', '=', 'ocs.id')
            ->select('order_costings.*', 'ocs.CS', 'ocs.SNo', 'ocs.Sname', 'ocs.Customer')
            ->when($request->filled('cs'), fn ($q) => $q->where('ocs.CS', 'like', '%'.$request->cs.'%'))
            ->orderByDesc('order_costings.id')->paginate(20);
        return view('admin.finance.order-costings', compact('costings'));
    }
    /**
     * Dashboard tài chính tổng quan
     */
    public function dashboard(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $year = substr($month, 0, 4);
        $m = substr($month, 5, 2);

        // === REVENUE từ daily_revenues ===
        $revenueData = DB::table('daily_revenues')
            ->join('revenue', 'daily_revenues.revenue_id', '=', 'revenue.id')
            ->leftJoin('mtp', 'revenue.CS', '=', 'mtp.CU')
            ->whereYear('daily_revenues.work_date', $year)
            ->whereMonth('daily_revenues.work_date', $m)
            ->select(
                DB::raw('SUM(daily_revenues.qty) as total_qty'),
                DB::raw('COUNT(DISTINCT daily_revenues.work_date) as working_days'),
                DB::raw('SUM(mtp.Qty_dis) as planned_qty')
            )
            ->first();

        $totalOutput = $revenueData->total_qty ?? 0;
        $workingDays = $revenueData->working_days ?? 0;
        $plannedQty = $revenueData->planned_qty ?? 0;

        // === Revenue by line ===
        $revenueByLine = DB::table('daily_revenues')
            ->join('revenue', 'daily_revenues.revenue_id', '=', 'revenue.id')
            ->whereYear('daily_revenues.work_date', $year)
            ->whereMonth('daily_revenues.work_date', $m)
            ->select('revenue.SewingLine', DB::raw('SUM(daily_revenues.qty) as qty'))
            ->groupBy('revenue.SewingLine')
            ->orderByDesc('qty')
            ->get();

        // === CHI PHÍ từ expenses ===
        $expenses = DB::table('expenses')
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $m)
            ->select(
                'category',
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $totalExpenses = $expenses->sum('total');

        // === COST ANALYSIS ===
        $costAnalyses = DB::table('cost_analyses')
            ->where('period', $month)
            ->get();

        $totalStandardCost = $costAnalyses->sum('standard_total_cost');
        $totalActualCost = $costAnalyses->sum('actual_total_cost');
        $totalProfit = $costAnalyses->sum('total_profit');
        $totalRevenue = $costAnalyses->sum('total_revenue');

        // === Daily revenue trend ===
        $dailyTrend = DB::table('daily_revenues')
            ->join('revenue', 'daily_revenues.revenue_id', '=', 'revenue.id')
            ->whereYear('daily_revenues.work_date', $year)
            ->whereMonth('daily_revenues.work_date', $m)
            ->select('daily_revenues.work_date', DB::raw('SUM(daily_revenues.qty) as qty'))
            ->groupBy('daily_revenues.work_date')
            ->orderBy('daily_revenues.work_date')
            ->get();

        // === Summary ===
        $summary = (object)[
            'total_output' => $totalOutput,
            'working_days' => $workingDays,
            'avg_daily_output' => $workingDays > 0 ? round($totalOutput / $workingDays) : 0,
            'planned_qty' => $plannedQty,
            'achievement_pct' => $plannedQty > 0 ? round(($totalOutput / $plannedQty) * 100, 1) : 0,
            'total_revenue' => $totalRevenue,
            'total_standard_cost' => $totalStandardCost,
            'total_actual_cost' => $totalActualCost,
            'total_profit' => $totalProfit,
            'total_expenses' => $totalExpenses,
            'profit_margin' => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0,
        ];

        return view('admin.finance.dashboard', compact(
            'summary', 'revenueByLine', 'expenses', 'costAnalyses', 'dailyTrend', 'month'
        ));
    }

    /**
     * Cost Analysis - Phân tích chi phí theo style
     */
    public function costAnalysis(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $analyses = DB::table('cost_analyses')
            ->when($request->filled('month'), fn($q) => $q->where('period', $month))
            ->when($request->filled('style_no'), fn($q) => $q->where('style_no', 'like', '%'.$request->style_no.'%'))
            ->orderBy('period', 'desc')
            ->paginate(20);

        return view('admin.finance.cost-analysis', compact('analyses', 'month'));
    }

    /**
     * Calculate cost analysis from BOM + actual production data
     */
    public function calculateCostAnalysis(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return back()->with('error', 'Invalid month format. Use YYYY-MM.');
        }
        $year = substr($month, 0, 4);
        $m = substr($month, 5, 2);

        set_time_limit(120);

        try {
            DB::beginTransaction();

            // Get styles produced in this month
            $styles = DB::table('ocs')
                ->join('mtp', 'ocs.CS', '=', 'mtp.CU')
                ->leftJoin('bom_headers', 'ocs.bom_header_id', '=', 'bom_headers.id')
                ->select(
                    'ocs.SNo as style_no',
                    'ocs.Sname as style_name',
                    'ocs.Customer as customer',
                    'ocs.id as ocs_id',
                    'bom_headers.id as bom_header_id',
                    'bom_headers.total_fabric_cost',
                    'bom_headers.total_trim_cost',
                DB::raw('SUM(mtp.Qty_dis) as total_qty_per_style'),
                'ocs.CMT as cmt_cost'
            )
            ->where(function ($q) use ($year, $m) {
                $q->whereYear('mtp.actual_sew_end', $year)
                  ->whereMonth('mtp.actual_sew_end', $m);
            })
            ->whereNotNull('ocs.bom_header_id') // Only styles with BOM
            ->groupBy('ocs.SNo', 'ocs.Sname', 'ocs.Customer', 'ocs.id', 'bom_headers.id',
                      'bom_headers.total_fabric_cost', 'bom_headers.total_trim_cost', 'ocs.CMT')
            ->get();

        // Group by style_no and aggregate
        $stylesByStyle = [];
        foreach ($styles as $s) {
            $key = $s->style_no;
            if (!isset($stylesByStyle[$key])) {
                $stylesByStyle[$key] = (object)[
                    'style_no' => $s->style_no,
                    'style_name' => $s->style_name ?? '',
                    'customer' => $s->customer ?? '',
                    'ocs_id' => $s->ocs_id,
                    'bom_header_id' => $s->bom_header_id,
                    'total_fabric_cost' => $s->total_fabric_cost ?? 0,
                    'total_trim_cost' => $s->total_trim_cost ?? 0,
                    'total_qty' => 0,
                    'cmt_cost' => $s->cmt_cost ?? 0,
                ];
            }
            $stylesByStyle[$key]->total_qty += (int)($s->total_qty_per_style ?? 0);
        }

        $count = 0;

        foreach ($stylesByStyle as $style) {
            $stdFabricCost = $style->total_fabric_cost ?? 0;
            $stdTrimCost = $style->total_trim_cost ?? 0;
            $stdCmtCost = $style->cmt_cost ?? 0;
            $qty = $style->total_qty ?: 1;

            // Standard per-unit cost (BOM total / qty)
            $stdFabricCostPerUnit = $qty > 0 ? $stdFabricCost / $qty : 0;
            $stdTrimCostPerUnit = $qty > 0 ? $stdTrimCost / $qty : 0;
            $stdTotalPerUnit = $stdFabricCostPerUnit + $stdTrimCostPerUnit + $stdCmtCost;

            // Actual costs from real data
            $actualFabricCostPerUnit = $stdFabricCostPerUnit; // Default = standard
            $actualTotalPerUnit = $stdTotalPerUnit;

            // Revenue: use CMT * 2.5 as estimated selling price (can be updated manually)
            $revenuePerUnit = $stdCmtCost > 0 ? $stdCmtCost * 2.5 : $stdTotalPerUnit * 1.3;

            $totalRevenue = $revenuePerUnit * $qty;
            $totalActualCost = $actualTotalPerUnit * $qty;
            $totalStandardCost = $stdTotalPerUnit * $qty;
            $totalProfit = $totalRevenue - $totalActualCost;
            $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;
            $variance = $totalActualCost - $totalStandardCost;
            $variancePct = $totalStandardCost > 0 ? round(($variance / $totalStandardCost) * 100, 2) : 0;

            // Upsert
            $existing = DB::table('cost_analyses')
                ->where('style_no', $style->style_no)
                ->where('period', $month)
                ->first();

            $data = [
                'style_no' => $style->style_no,
                'style_name' => $style->style_name ?? '',
                'customer' => $style->customer ?? '',
                'bom_header_id' => $style->bom_header_id,
                'ocs_id' => $style->ocs_id,
                'standard_fabric_cost' => $stdFabricCostPerUnit,
                'standard_trim_cost' => $stdTrimCostPerUnit,
                'standard_labor_cost' => 0,
                'standard_cmt_cost' => $stdCmtCost,
                'standard_overhead_cost' => 0,
                'standard_total_cost' => $stdTotalPerUnit,
                'actual_fabric_cost' => $actualFabricCostPerUnit,
                'actual_trim_cost' => $stdTrimCostPerUnit,
                'actual_labor_cost' => 0,
                'actual_cmt_cost' => $stdCmtCost,
                'actual_overhead_cost' => 0,
                'actual_total_cost' => $actualTotalPerUnit,
                'revenue_per_unit' => $revenuePerUnit,
                'total_qty' => $qty,
                'total_revenue' => $totalRevenue,
                'total_profit' => $totalProfit,
                'profit_margin_percent' => $profitMargin,
                'cost_variance' => $variance,
                'variance_percent' => $variancePct,
                'period' => $month,
                'notes' => 'Standard costs from BOM. Actual costs use BOM rates (update with real data). Revenue estimated at CMT×2.5.',
                'updated_at' => now(),
            ];

                if ($existing) {
                    DB::table('cost_analyses')->where('id', $existing->id)->update($data);
                } else {
                    $data['created_at'] = now();
                    DB::table('cost_analyses')->insert($data);
                }

                $count++;
            }

            DB::commit();

            return redirect()->route('admin.finance.cost-analysis', ['month' => $month])
                ->with('success', "Cost analysis calculated for $count styles in $month");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cost analysis calculation failed: ' . $e->getMessage());
            return back()->with('error', 'Calculation failed: ' . $e->getMessage());
        }
    }

    /**
     * Expenses Management
     */
    public function expenses(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $year = substr($month, 0, 4);
        $m = substr($month, 5, 2);

        $expenses = DB::table('expenses')
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $m)
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->category))
            ->orderBy('expense_date', 'desc')
            ->paginate(20);

        $totalByCategory = DB::table('expenses')
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $m)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();

        return view('admin.finance.expenses', compact('expenses', 'totalByCategory', 'month'));
    }

    public function storeExpense(Request $request)
    {
        $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|in:labor,overhead,utility,maintenance,other',
            'description' => 'required',
            'amount' => 'required|numeric|min:0',
        ]);

        DB::table('expenses')->insert([
            'expense_date' => $request->expense_date,
            'category' => $request->category,
            'description' => $request->description,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Expense recorded');
    }

    public function deleteExpense($id)
    {
        DB::table('expenses')->where('id', $id)->delete();
        return back()->with('success', 'Expense deleted');
    }

    /**
     * Profitability Report
     */
    public function profitability(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $report = DB::table('cost_analyses')
            ->where('period', $month)
            ->orderBy('total_profit', 'desc')
            ->get();

        $summary = (object)[
            'total_revenue' => $report->sum('total_revenue'),
            'total_actual_cost' => $report->sum('actual_total_cost'),
            'total_profit' => $report->sum('total_profit'),
            'avg_margin' => $report->count() > 0 ? $report->avg('profit_margin_percent') : 0,
            'best_style' => $report->sortByDesc('profit_margin_percent')->first()->style_no ?? 'N/A',
            'worst_style' => $report->sortBy('profit_margin_percent')->first()->style_no ?? 'N/A',
        ];

        return view('admin.finance.profitability', compact('report', 'summary', 'month'));
    }

    /**
     * Monthly Report
     */
    public function monthlyReport(Request $request)
    {
        $year = $request->input('year', now()->format('Y'));

        $monthlyData = DB::table('cost_analyses')
            ->whereYear('created_at', $year)
            ->select(
                'period',
                DB::raw('SUM(total_revenue) as revenue'),
                DB::raw('SUM(actual_total_cost) as actual_cost'),
                DB::raw('SUM(standard_total_cost) as standard_cost'),
                DB::raw('SUM(total_profit) as profit'),
                DB::raw('AVG(profit_margin_percent) as avg_margin'),
                DB::raw('COUNT(*) as style_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $expenseMonthly = DB::table('expenses')
            ->whereYear('expense_date', $year)
            ->select(
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as period"),
                DB::raw('SUM(amount) as total_expense')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // Merge expense data
        foreach ($monthlyData as $data) {
            $data->expenses = $expenseMonthly[$data->period]->total_expense ?? 0;
            $data->net_profit = $data->profit - $data->expenses;
        }

        return view('admin.finance.monthly', compact('monthlyData', 'year'));
    }
}
