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
        $items = DB::table('development_norm_items')->where('development_norm_id', $norm->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.development-norms.show', compact('norm', 'items'));
    }

    public function update(Request $request, int $cutsheetId): RedirectResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:4000',
            'items.*.yield_value' => 'required|numeric|min:0|max:99999999|decimal:0,4',
            'items.*.waste_percent' => 'required|numeric|min:0|max:100|decimal:0,2',
        ]);

        DB::transaction(function () use ($request, $cutsheetId, $data) {
            $norm = DB::table('development_norms')->where('cutsheet_id', $cutsheetId)->lockForUpdate()->first();
            abort_unless($norm, 404);
            $rows = DB::table('development_norm_items')->where('development_norm_id', $norm->id)->lockForUpdate()->get()->keyBy('id');
            if (array_diff(array_map('intval', array_keys($data['items'])), $rows->keys()->map(fn ($id) => (int) $id)->all())) {
                abort(404);
            }

            foreach ($data['items'] as $id => $values) {
                DB::table('development_norm_items')->where('id', (int) $id)->where('development_norm_id', $norm->id)->update([
                    'yield_value' => $values['yield_value'],
                    'waste_percent' => $values['waste_percent'],
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('admin.development-norms.show', $cutsheetId)->with('success', 'Development norms saved. The source BOM was not changed.');
    }
}
