<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DevelopmentNormController extends Controller
{
    public function index(): View
    {
        $orders = DB::table('development_norms as norm')
            ->leftJoin('ocs', 'ocs.id', '=', 'norm.cutsheet_id')
            ->select('norm.*', 'ocs.status as current_status')
            ->selectSub(DB::table('development_norm_items')->selectRaw('COUNT(*)')
                ->whereColumn('development_norm_items.development_norm_id', 'norm.id'), 'item_count')
            ->orderByDesc('norm.copied_at')->paginate(30)->withQueryString();

        return view('admin.development-norms.index', compact('orders'));
    }

    public function show(int $cutsheetId): View
    {
        $norm = DB::table('development_norms')->where('cutsheet_id', $cutsheetId)->first();
        abort_unless($norm, 404);
        $isAdmin = auth()->user()?->role === 'admin';
        $sizes = DB::table('development_norm_sizes')->where('development_norm_id', $norm->id)
            ->orderBy('sort_order')->orderBy('id')->get();
        $items = DB::table('development_norm_items')->where('development_norm_id', $norm->id)
            ->orderBy('sort_order')->orderBy('id')->get();
        $rates = DB::table('development_norm_item_sizes as rate')
            ->join('development_norm_sizes as size', 'size.id', '=', 'rate.development_norm_size_id')
            ->whereIn('rate.development_norm_item_id', $items->pluck('id'))
            ->select('rate.development_norm_item_id', 'size.id as size_id', 'rate.yield_value')
            ->get()->groupBy('development_norm_item_id');
        foreach ($items as $item) {
            $item->size_rates = ($rates[$item->id] ?? collect())->keyBy('size_id');
        }

        return view('admin.development-norms.show', compact('norm', 'items', 'sizes', 'isAdmin'));
    }

    public function destroyItems(Request $request, int $cutsheetId): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'item_ids' => 'required|array|min:1|max:4000',
            'item_ids.*' => 'required|integer|distinct',
        ]);

        DB::transaction(function () use ($cutsheetId, $data) {
            $norm = DB::table('development_norms')->where('cutsheet_id', $cutsheetId)->lockForUpdate()->first();
            abort_unless($norm, 404);
            $itemIds = array_map('intval', $data['item_ids']);
            $matchingIds = DB::table('development_norm_items')->where('development_norm_id', $norm->id)
                ->whereIn('id', $itemIds)->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            abort_unless(count($matchingIds) === count($itemIds), 404);
            DB::table('development_norm_items')->where('development_norm_id', $norm->id)->whereIn('id', $itemIds)->delete();
        });

        return redirect()->route('admin.development-norms.show', $cutsheetId)
            ->with('success', count($data['item_ids']) . ' material(s) deleted from this Development Norm. The source BOM was not changed.');
    }

    public function update(Request $request, int $cutsheetId): RedirectResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:4000',
            'items.*.size_rates' => 'required|array|min:1',
            'items.*.size_rates.*' => 'required|numeric|min:0|max:99999999|decimal:0,4',
            'items.*.waste_percent' => 'required|numeric|min:0|max:100|decimal:0,2',
        ]);

        DB::transaction(function () use ($request, $cutsheetId, $data) {
            $norm = DB::table('development_norms')->where('cutsheet_id', $cutsheetId)->lockForUpdate()->first();
            abort_unless($norm, 404);
            $rows = DB::table('development_norm_items')->where('development_norm_id', $norm->id)->lockForUpdate()->get()->keyBy('id');
            if (array_diff(array_map('intval', array_keys($data['items'])), $rows->keys()->map(fn ($id) => (int) $id)->all())) {
                abort(404);
            }
            $sizes = DB::table('development_norm_sizes')->where('development_norm_id', $norm->id)->lockForUpdate()->get()->keyBy('id');
            if ($sizes->isEmpty()) abort(409, 'This Development NORM has no size breakdown.');
            $totalQty = (float) $sizes->sum('quantity');

            foreach ($data['items'] as $id => $values) {
                $sizeRates = $values['size_rates'];
                $providedSizeIds = array_map('intval', array_keys($sizeRates));
                $expectedSizeIds = $sizes->keys()->map(fn ($sizeId) => (int) $sizeId)->all();
                if (array_diff($providedSizeIds, $expectedSizeIds) || array_diff($expectedSizeIds, $providedSizeIds)) {
                    abort(422, 'The submitted size breakdown does not match this Development NORM.');
                }
                $weightedNeed = 0.0;
                foreach ($sizeRates as $sizeId => $yieldValue) {
                    $size = $sizes[(int) $sizeId];
                    $weightedNeed += (float) $yieldValue * (float) $size->quantity;
                    DB::table('development_norm_item_sizes')->where('development_norm_item_id', (int) $id)
                        ->where('development_norm_size_id', (int) $sizeId)->update([
                            'yield_value' => $yieldValue, 'updated_at' => now(),
                        ]);
                }
                $averageYield = $totalQty > 0 ? $weightedNeed / $totalQty : 0;
                DB::table('development_norm_items')->where('id', (int) $id)->where('development_norm_id', $norm->id)->update([
                    'yield_value' => round($averageYield, 4),
                    'waste_percent' => $values['waste_percent'],
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('admin.development-norms.show', $cutsheetId)->with('success', 'Development norms saved. The source BOM was not changed.');
    }
}
