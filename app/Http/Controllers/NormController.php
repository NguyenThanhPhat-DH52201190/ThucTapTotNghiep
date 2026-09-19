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
            ->when($request->filled('cutsheet_id'), fn ($q) => $q->where('norm.cutsheet_id', $request->integer('cutsheet_id')))
            ->when($request->filled('cs'), fn ($q) => $q->where('ocs.CS', 'like', '%' . trim($request->cs) . '%'))
            ->when($request->filled('material'), function ($q) use ($request) {
                $term = '%' . trim($request->material) . '%';
                $q->where(fn ($sub) => $sub->where('norm.material_code', 'like', $term)->orWhere('norm.material_name', 'like', $term));
            })
            ->select('norm.*', 'ocs.CS', 'ocs.SNo', 'ocs.Sname', 'ocs.Customer', 'ocs.Color as garment_color',
                'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version')
            ->orderBy('ocs.CS')->orderBy('norm.id');
    }

    public function materials(Request $request, OrderMaterialRequirementService $service)
    {
        $orders = DB::table('ocs')
            ->leftJoin('bom_headers', 'bom_headers.id', '=', 'ocs.bom_header_id')
            ->whereNotNull('ocs.bom_header_id')
            ->when($request->filled('cs'), fn ($q) => $q->where('ocs.CS', 'like', '%' . trim($request->cs) . '%'))
            ->select('ocs.id', 'ocs.CS', 'ocs.SNo', 'ocs.Sname', 'ocs.Customer', 'ocs.Color', 'ocs.Qty',
                'ocs.status', 'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version')
            ->orderBy('ocs.CS')->paginate(30)->withQueryString();
        return view('admin.norm.index', compact('orders'));
    }

    public function materialDetail(int $id, OrderMaterialRequirementService $service)
    {
        $order = DB::table('ocs')->whereNotNull('bom_header_id')->find($id);
        if (!$order) abort(404);
        $service->sync($id);
        $request = request()->merge(['cutsheet_id' => $id]);
        $rows = $this->query($request)->paginate(50)->withQueryString();
        return view('admin.norm.materials', compact('rows', 'order'));
    }

    public function exportMaterials(Request $request, OrderMaterialRequirementService $service)
    {
        $this->syncOrders($service, $request->integer('cutsheet_id') ?: null);
        $rows = $this->query($request)->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('NORM Materials');
        $sheet->fromArray(['CS', 'Style', 'Style Name', 'Customer', 'Garment Color', 'BOM', 'Material Code', 'Description', 'Type', 'Material Color', 'Material Size', 'Unit', 'Product Qty', 'Yield', 'Waste %', 'Required', 'On Hand', 'Reserved', 'Available', 'Shortage', 'Status'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([$row->CS, $row->SNo, $row->Sname, $row->Customer, $row->garment_color,
                trim(($row->bom_style ?? '') . ' ' . ($row->bom_version ?? '')), $row->material_code, $row->material_name,
                $row->material_type, $row->material_color, $row->material_size, $row->unit, (float) $row->product_qty,
                (float) $row->consumption_rate, (float) $row->waste_percent, (float) $row->required_qty,
                (float) $row->on_hand_qty, (float) $row->reserved_qty, (float) $row->available_qty,
                (float) $row->shortage_qty, ucfirst($row->stock_status)], null, 'A' . ($index + 2));
        }
        $sheet->getStyle('A1:U1')->getFont()->setBold(true);
        foreach (range('A', 'U') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $lastRow = max(2, $rows->count() + 1);
        $sheet->getStyle("M2:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("N2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
        $sheet->getStyle("O2:O{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("P2:T{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'norm-materials-' . now()->format('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
