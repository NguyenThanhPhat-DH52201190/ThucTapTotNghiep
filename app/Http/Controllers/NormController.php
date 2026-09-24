<?php

namespace App\Http\Controllers;

use App\Services\OrderMaterialRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NormController extends Controller
{
    private function syncOrders(OrderMaterialRequirementService $service, ?int $cutsheetId = null): void
    {
        $query = DB::table('ocs')->whereNotNull('bom_header_id');
        if ($cutsheetId) $query->where('id', $cutsheetId);
        foreach ($query->pluck('id') as $id) $service->sync((int) $id);
    }

    private function query(Request $request)
    {
        return DB::table('order_material_requirements as norm')
            ->join('ocs', 'ocs.id', '=', 'norm.cutsheet_id')
            ->leftJoin('bom_headers', 'bom_headers.id', '=', 'norm.bom_header_id')
            ->leftJoin('bom_items as source_item', 'source_item.id', '=', 'norm.bom_item_id')
            ->leftJoin('norm_confirmations as confirmation', function ($join) {
                $join->on('confirmation.cutsheet_id', '=', 'norm.cutsheet_id')->on('confirmation.bom_item_id', '=', 'norm.bom_item_id');
            })
            ->leftJoin('norm_material_replacements as replacement', 'replacement.id', '=', 'norm.replacement_id')
            ->when($request->filled('cutsheet_id'), fn ($q) => $q->where('norm.cutsheet_id', $request->integer('cutsheet_id')))
            ->when($request->filled('cs'), fn ($q) => $q->where('ocs.CS', 'like', '%' . trim($request->cs) . '%'))
            ->when($request->filled('material'), function ($q) use ($request) {
                $term = '%' . trim($request->material) . '%';
                $q->where(fn ($sub) => $sub->where('norm.material_code', 'like', $term)->orWhere('norm.material_name', 'like', $term));
            })
            ->select('norm.*', 'ocs.CS', 'ocs.SNo', 'ocs.Sname', 'ocs.Customer', 'ocs.Color as garment_color',
                'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version',
                'source_item.consumption_rate as yield_plan', 'source_item.waste_percent as waste_plan',
                DB::raw('COALESCE(replacement.yield_confirmed, confirmation.yield_confirmed) as yield_confirmed'), DB::raw('COALESCE(replacement.waste_confirmed, confirmation.waste_confirmed) as waste_confirmed'), 'confirmation.revision as confirmation_revision',
                'confirmation.bom_yield_at_confirmation', 'confirmation.bom_waste_at_confirmation')
            ->orderBy('ocs.CS')->orderBy('norm.id');
    }

    private function ordersWithImages()
    {
        return DB::table('ocs')
            ->leftJoin('bom_headers', 'bom_headers.id', '=', 'ocs.bom_header_id')
            ->leftJoin('bom_headers as template', 'template.id', '=', 'bom_headers.template_id')
            ->whereNotNull('ocs.bom_header_id')
            ->select('ocs.*', 'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version')
            ->selectRaw(\App\Services\CustomerStyleService::imageSql('ocs', 'SNo').' as image_path')
            ->selectRaw('CASE WHEN '.\App\Services\CustomerStyleService::imageSql('bom_headers', 'style_no').' IS NOT NULL THEN bom_headers.id WHEN '.\App\Services\CustomerStyleService::imageSql('template', 'style_no').' IS NOT NULL THEN template.id ELSE NULL END as bom_image_id');
    }

    public function materials(Request $request, OrderMaterialRequirementService $service)
    {
        $orders = $this->ordersWithImages()
            ->when($request->filled('cs'), fn ($q) => $q->where('ocs.CS', 'like', '%' . trim($request->cs) . '%'))
            ->orderBy('ocs.CS')->paginate(30)->withQueryString();
        return view('admin.norm.index', compact('orders'));
    }

    public function materialDetail(int $id, OrderMaterialRequirementService $service)
    {
        $order = $this->ordersWithImages()->where('ocs.id', $id)->first();
        if (!$order) abort(404);
        $service->sync($id);
        $request = request()->merge(['cutsheet_id' => $id]);
        $rows = $this->query($request)
            ->leftJoin('materials as material', 'material.id', '=', 'norm.material_id')
            ->addSelect('material.id as image_material_id', 'material.image_path as material_image_path')
            ->paginate(50)->withQueryString();
        return view('admin.norm.materials', compact('rows', 'order'));
    }

    public function updateConfirmed(Request $request, int $id, OrderMaterialRequirementService $service, \App\Services\AuditTrailService $audit)
    {
        $data = $request->validate([
            'rows' => 'required|array|min:1|max:50',
            'rows.*.bom_item_id' => 'required|integer|distinct',
            'rows.*.revision' => 'required|integer|min:0',
            'rows.*.yield_confirmed' => 'nullable|numeric|min:0|max:99999999|decimal:0,4',
            'rows.*.waste_confirmed' => 'nullable|numeric|min:0|max:100|decimal:0,2',
            'rows.*.yield_plan' => 'required|numeric', 'rows.*.waste_plan' => 'required|numeric',
            'reason' => 'nullable|string|max:1000',
        ]);
        DB::transaction(function () use ($request, $id, $data, $service, $audit) {
            $order = DB::table('ocs')->where('id', $id)->lockForUpdate()->first();
            abort_unless($order, 404);
            $items = DB::table('bom_items')->where('bom_header_id', $order->bom_header_id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($data['rows'] as $index => $row) {
                $item = $items->get($row['bom_item_id']);
                if (!$item) throw \Illuminate\Validation\ValidationException::withMessages(["rows.$index.bom_item_id" => 'This material is no longer in the order BOM. Reload NORM.']);
                $before = DB::table('norm_confirmations')->where('cutsheet_id', $id)->where('bom_item_id', $item->id)->first();
                if ((int) ($before->revision ?? 0) !== (int) $row['revision']
                    || abs((float) $row['yield_plan'] - (float) $item->consumption_rate) > 0.00001
                    || abs((float) $row['waste_plan'] - (float) $item->waste_percent) > 0.00001) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['rows' => 'NORM or BOM changed in another session. Reload before saving.']);
                }
                $values = [
                    'yield_confirmed' => $row['yield_confirmed'] ?? null,
                    'waste_confirmed' => $row['waste_confirmed'] ?? null,
                    'bom_yield_at_confirmation' => $item->consumption_rate,
                    'bom_waste_at_confirmation' => $item->waste_percent,
                    'revision' => (int) ($before->revision ?? 0) + 1,
                    'updated_by' => $request->user()->id, 'updated_at' => now(),
                ];
                if ($before) DB::table('norm_confirmations')->where('id', $before->id)->update($values);
                else DB::table('norm_confirmations')->insert($values + ['cutsheet_id' => $id, 'bom_item_id' => $item->id, 'created_at' => now()]);
                $audit->record('norm_confirmed_updated', 'ocs', $id, $request->user()->id,
                    $before ? (array) $before : ['bom_item_id' => $item->id],
                    $values + ['bom_item_id' => $item->id], $data['reason'] ?? null);
            }
            $service->sync($id);
        });
        return redirect()->route('admin.norm.materials.show', ['id' => $id, 'page' => $request->query('page', 1)])
            ->with('success', 'NORM confirmed values saved and requirements recalculated. Existing PO, MRP runs and inventory transactions are unchanged; rerun MRP to use the new requirements.');
    }

    public function exportMaterials(Request $request, OrderMaterialRequirementService $service)
    {
        $this->syncOrders($service, $request->integer('cutsheet_id') ?: null);
        $rows = $this->query($request)->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('NORM Materials');
        $sheet->fromArray(['CS', 'Style', 'Style Name', 'Customer', 'Garment Color', 'BOM', 'Material Code', 'Description', 'Type', 'Material Color', 'Material Size', 'Unit', 'Product Qty', 'Yield plan', 'Yield confirmed', 'watse confirmed', 'Required', 'On Hand', 'Reserved', 'Available', 'Shortage', 'Status'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([$row->CS, $row->SNo, $row->Sname, $row->Customer, $row->garment_color,
                trim(($row->bom_style ?? '') . ' ' . ($row->bom_version ?? '')), $row->material_code, $row->material_name,
                $row->material_type, $row->material_color, $row->material_size, $row->unit, (float) $row->product_qty,
                (float) $row->yield_plan, $row->yield_confirmed === null ? null : (float) $row->yield_confirmed,
                (float) $row->waste_percent, (float) $row->required_qty,
                (float) $row->on_hand_qty, (float) $row->reserved_qty, (float) $row->available_qty,
                (float) $row->shortage_qty, ucfirst($row->stock_status)], null, 'A' . ($index + 2));
        }
        $sheet->getStyle('A1:V1')->getFont()->setBold(true);
        foreach (range('A', 'V') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $lastRow = max(2, $rows->count() + 1);
        $sheet->getStyle("M2:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("N2:O{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
        $sheet->getStyle("P2:P{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("Q2:U{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'norm-materials-' . now()->format('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
