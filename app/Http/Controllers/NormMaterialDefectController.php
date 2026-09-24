<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class NormMaterialDefectController extends Controller
{
    private function materials(object $order)
    {
        return DB::table('order_material_requirements as norm')
            ->join('bom_items', 'bom_items.id', '=', 'norm.bom_item_id')
            ->where('norm.cutsheet_id', $order->id)->where('bom_items.bom_header_id', $order->bom_header_id)
            ->where('norm.bom_header_id', $order->bom_header_id)
            ->select('norm.*')->orderBy('norm.material_code')->get();
    }

    public function index(int $id)
    {
        $order = DB::table('ocs')->find($id);
        abort_unless($order, 404);
        $materials = $this->materials($order);
        $history = DB::table('norm_material_defects as defects')->leftJoin('users', 'users.id', '=', 'defects.created_by')
            ->where('cutsheet_id', $id)->select('defects.*', 'users.name as recorded_by')
            ->orderByDesc('defects.id')->paginate(20);
        return view('admin.norm.defects', compact('order', 'materials', 'history'));
    }

    public function store(Request $request, int $id)
    {
        $data = $request->validate([
            'submission_key' => 'required|uuid', 'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'items' => 'required|array|list|min:1|max:20', 'items.*.bom_item_id' => 'required|integer|distinct',
            'items.*.defect_qty' => 'required|numeric|gt:0|max:99999999|decimal:0,4',
            'items.*.replacement_qty' => 'required|numeric|min:0|max:99999999|decimal:0,4',
            'items.*.disposition' => 'required|in:reuse,return_supplier,scrap',
            'items.*.reason' => 'required|string|max:1000',
            'items.*.image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);
        $paths = [];
        try {
            DB::transaction(function () use ($request, $id, $data, &$paths) {
                $order = DB::table('ocs')->where('id', $id)->lockForUpdate()->first();
                abort_unless($order, 404);
                // A retry/double click of the same form must not record the loss twice.
                if (DB::table('norm_material_defects')->where('cutsheet_id', $id)->where('submission_key', $data['submission_key'])->exists()) return;
                $materials = $this->materials($order)->keyBy('bom_item_id');
                foreach ($data['items'] as $index => $item) {
                    $material = $materials->get($item['bom_item_id']);
                    if (!$material) throw ValidationException::withMessages(["items.$index.bom_item_id" => 'Select a material from the current NORM for this CU.']);
                    if ((float) $item['replacement_qty'] > (float) $item['defect_qty']) throw ValidationException::withMessages(["items.$index.replacement_qty" => 'Requested replacement cannot exceed defective quantity.']);
                    $path = null;
                    if ($request->hasFile("items.$index.image")) {
                        $path = $request->file("items.$index.image")->store('norm-defect-images', 'local');
                        if (!$path) throw new \RuntimeException('Unable to store defect image.');
                        $paths[] = $path;
                    }
                    DB::table('norm_material_defects')->insert([
                        'cutsheet_id' => $id, 'submission_key' => $data['submission_key'], 'line_no' => $index,
                        'bom_item_id' => $material->bom_item_id, 'material_id' => $material->material_id,
                        'material_code' => $material->material_code, 'material_name' => $material->material_name,
                        'material_color' => $material->material_color, 'material_size' => $material->material_size, 'unit' => $material->unit,
                        'occurred_on' => $data['occurred_on'], 'defect_qty' => $item['defect_qty'], 'replacement_qty' => $item['replacement_qty'],
                        'disposition' => $item['disposition'], 'reason' => $item['reason'], 'image_path' => $path,
                        'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            throw $e;
        }
        return redirect()->route('admin.norm.defects', $id)->with('success', 'Material defects recorded. Replacement quantities are requests only; no stock has been issued.');
    }

    public function image(int $id, int $defect)
    {
        $path = DB::table('norm_material_defects')->where('cutsheet_id', $id)->where('id', $defect)->value('image_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }
}
