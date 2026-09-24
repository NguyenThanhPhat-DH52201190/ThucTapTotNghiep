<?php

namespace App\Http\Controllers;

use App\Services\{AuditTrailService, DeliveryBillService, OrderMaterialRequirementService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NormMaterialReplacementController extends Controller
{
    public function index(int $id, OrderMaterialRequirementService $requirements, DeliveryBillService $delivery)
    {
        $order = DB::table('ocs')->find($id);
        abort_unless($order, 404);
        $needs = $requirements->sync($id);
        $remaining = $delivery->remaining($id, $needs);
        $sources = $needs->whereNotNull('bom_item_id')->values()->map(function ($row) use ($delivery, $remaining) {
            $row->remaining = min((float) $row->required_qty, $remaining[$delivery->key($row)] ?? 0);
            $row->fingerprint = $this->fingerprint($row);
            return $row;
        });
        $materials = DB::table('materials')->orderBy('internal_code')->get();
        $defects = DB::table('norm_material_defects')->where('cutsheet_id', $id)->orderByDesc('id')->get();
        $history = DB::table('norm_material_replacements')->where('cutsheet_id', $id)->orderByDesc('id')->get();
        foreach ($defects as $defect) {
            $defect->remaining = max(0, (float) $defect->replacement_qty - (float) $history->where('defect_id', $defect->id)->sum('source_qty'));
        }
        return view('admin.norm.replacements', compact('order', 'sources', 'materials', 'defects', 'history'));
    }

    private function fingerprint(object $row): string
    {
        return hash('sha256', json_encode([(int) $row->bom_item_id, (int) $row->material_id,
            $row->material_color, $row->material_size, $row->unit,
            (float) $row->product_qty, (float) $row->consumption_rate, (float) $row->waste_percent, (float) $row->required_qty]));
    }

    public function store(Request $request, int $id, OrderMaterialRequirementService $requirements, DeliveryBillService $delivery)
    {
        $data = $request->validate([
            'submission_key' => 'required|uuid', 'bom_item_id' => 'required|integer',
            'fingerprint' => 'required|string|size:64', 'material_id' => 'required|integer|exists:materials,id',
            'mode' => 'required|in:remaining,defect', 'defect_id' => 'nullable|required_if:mode,defect|integer',
            'source_qty' => 'required|numeric|gt:0|max:99999999|decimal:0,4',
            'yield_confirmed' => 'required|numeric|gt:0|max:99999999|decimal:0,4',
            'waste_confirmed' => 'required|numeric|min:0|max:100|decimal:0,2',
            'reason' => 'required|string|max:1000',
        ]);
        DB::transaction(function () use ($request, $id, $data, $requirements, $delivery) {
            $order = DB::table('ocs')->where('id', $id)->lockForUpdate()->first();
            abort_unless($order, 404);
            $existing = DB::table('norm_material_replacements')->where('submission_key', $data['submission_key'])->first();
            if ($existing) { abort_unless((int) $existing->cutsheet_id === $id, 409); return; }
            if (!in_array($order->status, ['pending', 'confirmed', 'in_production', 'released'])) {
                throw ValidationException::withMessages(['mode' => 'Only active CUs can replace materials.']);
            }
            $needs = $requirements->sync($id);
            $source = $needs->firstWhere('bom_item_id', $data['bom_item_id']);
            if (!$source || $this->fingerprint($source) !== $data['fingerprint']) {
                throw ValidationException::withMessages(['bom_item_id' => 'NORM changed. Reload this page before replacing material.']);
            }
            $material = DB::table('materials')->where('id', $data['material_id'])->lockForUpdate()->firstOrFail();
            if ((int) $material->id === (int) $source->material_id) {
                throw ValidationException::withMessages(['material_id' => 'Select a different material code.']);
            }
            $factor = (float) $source->consumption_rate * (1 + (float) $source->waste_percent / 100);
            if ($factor <= 0) throw ValidationException::withMessages(['source_qty' => 'Confirm a positive source yield first.']);
            $defectId = null;
            if ($data['mode'] === 'defect') {
                $defect = DB::table('norm_material_defects')->where('cutsheet_id', $id)->where('id', $data['defect_id'])->lockForUpdate()->first();
                if (!$defect || (int) $defect->bom_item_id !== (int) $source->bom_item_id || $delivery->key($defect) !== $delivery->key($source)) {
                    throw ValidationException::withMessages(['defect_id' => 'Select a defect for this source material and CU.']);
                }
                $limit = max(0, (float) $defect->replacement_qty - (float) DB::table('norm_material_replacements')->where('defect_id', $defect->id)->sum('source_qty'));
                $defectId = $defect->id;
            } else {
                $remaining = $delivery->remaining($id, $needs, true);
                $limit = min((float) $source->required_qty, $remaining[$delivery->key($source)] ?? 0);
            }
            if ((float) $data['source_qty'] > $limit + .00001) {
                throw ValidationException::withMessages(['source_qty' => 'Quantity exceeds the remaining replaceable quantity ('.round($limit, 4).').']);
            }
            $productQty = (float) $data['source_qty'] / $factor;
            $required = round($productQty * (float) $data['yield_confirmed'] * (1 + (float) $data['waste_confirmed'] / 100), 4);
            if ($required <= 0 || $required > 99999999) throw ValidationException::withMessages(['yield_confirmed' => 'Replacement requirement must be between 0.0001 and 99999999.']);
            $snapshot = ['material_id' => $material->id, 'material_code' => $material->internal_code,
                'material_name' => $material->material_name, 'material_type' => $material->material_type,
                'material_color' => $material->color, 'material_size' => $material->size, 'unit' => $material->unit];
            $replacementId = DB::table('norm_material_replacements')->insertGetId([
                'cutsheet_id' => $id, 'bom_header_id' => $order->bom_header_id, 'bom_item_id' => $source->bom_item_id,
                'defect_id' => $defectId, 'submission_key' => $data['submission_key'], 'material_id' => $material->id,
                'source_snapshot' => json_encode($source), 'material_snapshot' => json_encode($snapshot),
                'source_qty' => $data['source_qty'], 'product_qty' => round($productQty, 4),
                'yield_confirmed' => $data['yield_confirmed'], 'waste_confirmed' => $data['waste_confirmed'],
                'required_qty' => $required, 'reason' => $data['reason'], 'created_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $requirements->sync($id);
            app(AuditTrailService::class)->record('norm_material_replaced', 'ocs', $id, $request->user()->id,
                (array) $source, ['replacement_id' => $replacementId, 'material' => $snapshot, 'required_qty' => $required], $data['reason']);
        });
        return redirect()->route('admin.norm.materials.show', $id)->with('success', 'Material replacement saved for this CU. Review existing reservations/requisitions and rerun MRP if needed.');
    }
}
