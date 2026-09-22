<?php

namespace App\Http\Controllers;

use App\Services\AuditTrailService;
use App\Services\OrderMaterialRequirementService;
use App\Services\StockRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockRecordController extends Controller
{
    public function index(Request $request)
    {
        $balances = DB::table('inventory_balances')->select('material_id')
            ->selectRaw('SUM(balance_qty) as on_hand, SUM(reserved_qty) as reserved')->groupBy('material_id');
        $records = DB::table('stock_records as record')->join('materials', 'materials.id', '=', 'record.material_id')
            ->leftJoinSub($balances, 'stock', 'stock.material_id', '=', 'record.material_id')
            ->select('record.*', 'materials.internal_code', 'materials.material_name', 'materials.unit', 'materials.color', 'materials.size')
            ->selectRaw('COALESCE(stock.on_hand, 0) as on_hand, COALESCE(stock.reserved, 0) as reserved')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->q) . '%';
                $q->where(fn ($q) => $q->where('materials.internal_code', 'like', $term)->orWhere('materials.material_name', 'like', $term));
            })->orderBy('record.sort_order')->orderBy('materials.internal_code')->paginate(30)->withQueryString();
        return view('admin.stock-records.index', compact('records'));
    }

    public function sync(Request $request, StockRecordService $service, AuditTrailService $audit)
    {
        $added = $service->importInventory();
        $audit->record('stock_records_initialized', 'stock_record', null, $request->user()->id, [], ['added' => $added]);
        return back()->with('success', "$added new material records added. Existing opening balances were preserved.");
    }

    public function position(Request $request, int $id, AuditTrailService $audit)
    {
        $data = $request->validate(['sort_order' => 'required|integer|min:1|max:1000000']);
        DB::transaction(function () use ($id, $data, $request, $audit) {
            $record = DB::table('stock_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            DB::table('stock_records')->where('id', $id)->update($data + ['updated_at' => now()]);
            $audit->record('stock_record_reordered', 'stock_record', $id, $request->user()->id, ['sort_order' => $record->sort_order], $data);
        });
        return back()->with('success', 'Material order saved.');
    }

    public function show(Request $request, int $id, StockRecordService $service, OrderMaterialRequirementService $requirements)
    {
        $record = DB::table('stock_records')->find($id);
        abort_unless($record, 404);
        $material = DB::table('materials')->find($record->material_id);
        abort_unless($material, 404);
        $balances = DB::table('inventory_balances as balance')->leftJoin('warehouses', 'warehouses.id', '=', 'balance.warehouse_id')
            ->where('balance.material_id', $material->id)->select('balance.*', 'warehouses.name as warehouse_name')->orderBy('balance.id')->get();
        $plan = $service->plan($record, $requirements);
        $movements = DB::table('inventory_transactions')->where('material_id', $material->id);
        $ledgerQty = (float) $record->opening_qty + (float) (clone $movements)->where('id', '>', $record->opening_transaction_id)->sum('quantity');
        $historyOpening = (float) $record->opening_qty;
        if ($request->input('history') === 'all') {
            $historyOpening -= (float) (clone $movements)->where('id', '<=', $record->opening_transaction_id)->sum('quantity');
        } else {
            $movements->where('id', '>', $record->opening_transaction_id);
        }
        $transactions = (clone $movements)->orderBy('id')->paginate(50, ['*'], 'history_page')->withQueryString();
        $running = $historyOpening;
        if ($transactions->count()) {
            $running += (float) (clone $movements)->where('id', '<', $transactions->first()->id)->sum('quantity');
        }
        foreach ($transactions as $transaction) {
            $running += (float) $transaction->quantity;
            $transaction->running_qty = $running;
        }
        $actors = DB::table('users')->whereIn('id', $transactions->pluck('created_by')->filter())->pluck('name', 'id');
        $issueOrders = DB::table('material_issues as issue')->join('material_requisitions as req', 'req.id', '=', 'issue.requisition_id')
            ->join('ocs', 'ocs.id', '=', 'req.cutsheet_id')->whereIn('issue.id', $transactions->where('reference_type', 'MATERIAL_ISSUE')->pluck('reference_id'))
            ->pluck('ocs.CS', 'issue.id');
        $boms = DB::table('bom_headers')->where('bom_kind', 'template')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('bom_items')->whereColumn('bom_items.bom_header_id', 'bom_headers.id')->where('material_id', $material->id))
            ->orderBy('style_no')->get();
        return view('admin.stock-records.show', compact('record', 'material', 'balances', 'plan', 'transactions', 'ledgerQty', 'historyOpening', 'actors', 'issueOrders', 'boms'));
    }

    public function priorities(Request $request, int $id, StockRecordService $service, AuditTrailService $audit)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:0', 'priorities' => 'required|array|min:1',
            'priorities.*.cutsheet_id' => 'required|integer|distinct',
            'priorities.*.sort_order' => 'required|integer|min:1|max:1000000',
        ]);
        DB::transaction(function () use ($data, $id, $request, $service, $audit) {
            $record = DB::table('stock_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            if ((int) $record->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['priorities' => 'Priorities changed in another session. Reload this page before saving.']);
            }
            $allowed = $service->activeOrders($record->material_id)->pluck('id');
            if (collect($data['priorities'])->pluck('cutsheet_id')->diff($allowed)->isNotEmpty()) {
                throw ValidationException::withMessages(['priorities' => 'An order no longer uses this material or is no longer active. Reload this page.']);
            }
            $before = DB::table('stock_record_priorities')->where('stock_record_id', $id)->get()->toArray();
            foreach ($data['priorities'] as $entry) {
                DB::table('stock_record_priorities')->updateOrInsert(['stock_record_id' => $id, 'cutsheet_id' => $entry['cutsheet_id']],
                    ['sort_order' => $entry['sort_order'], 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('stock_records')->where('id', $id)->increment('revision', 1, ['updated_at' => now()]);
            $audit->record('stock_priorities_changed', 'stock_record', $id, $request->user()->id, $before, $data['priorities']);
        });
        return back()->with('success', 'Priorities saved and projected stock recalculated. Actual inventory was not changed.');
    }
}
