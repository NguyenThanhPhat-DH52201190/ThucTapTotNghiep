<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SubconContractController extends Controller
{
    private const PPIC_EDITABLE_FIELDS = [
        'unit_price', 'contract_no', 'contract_date', 'appendix_no', 'appendix_date', 'tax_code',
        'processing_stage', 'out_date', 'truck_plate', 'in_date', 'invoice_no', 'invoice_date', 'actual_received_qty', 'notes',
    ];
    private const ACCOUNTANT_EDITABLE_FIELDS = ['payment_date', 'notes'];
    private const IMEX_EDITABLE_FIELDS = [
        'customs_doc_no', 'customs_doc_date', 'form_12', 'form_12_approved_date', 'form_18a', 'form_18a_received_date',
        'highlight_color', 'notes',
    ];

    public function index(Request $request)
    {
        $subconLines = DB::table('colors')->where('is_active', 1)->where('cate', 'Subcon')->pluck('name');
        $contracts = DB::table('mtp')
            ->join('colors', function ($join) {
                $join->on('mtp.Line', '=', 'colors.name')->where('colors.cate', '=', 'Subcon')->where('colors.is_active', '=', 1);
            })
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->leftJoin('subcon_contracts', 'mtp.id', '=', 'subcon_contracts.mtp_id')
            ->when($request->filled('line'), fn ($q) => $q->where('mtp.Line', $request->input('line')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->input('search'));
                $q->where(function ($query) use ($search) {
                    $query->where('mtp.CU', 'like', "%{$search}%")
                        ->orWhere('ocs.SNo', 'like', "%{$search}%")
                        ->orWhere('ocs.ONum', 'like', "%{$search}%")
                        ->orWhere('ocs.Customer', 'like', "%{$search}%");
                });
            })
            // Keep the source plan id distinct from subcon_contracts.mtp_id. The
            // left-joined table's mtp_id is NULL for rows not saved yet.
            ->select('mtp.id as masterplan_id', 'mtp.CU', 'mtp.Line', 'mtp.LineColor as line_color', 'mtp.Qty_dis', 'mtp.Norm_date', 'mtp.fabric_issue_date', 'mtp.SoTK as masterplan_sotk', 'mtp.ShipDate2 as masterplan_ship_date',
                'ocs.SNo as style', 'ocs.ONum as po', 'ocs.Customer as customer', 'ocs.Color as garment_color',
                'subcon_contracts.*')
            ->orderByRaw("CASE WHEN subcon_contracts.out_date IS NULL THEN 0 WHEN subcon_contracts.truck_plate IS NULL OR TRIM(subcon_contracts.truck_plate) = '' THEN 1 ELSE 2 END")
            ->orderBy('subcon_contracts.out_date')->orderBy('mtp.Line')->orderBy('mtp.CU')->get();

        return view('admin.subcon-contracts.index', compact('contracts', 'subconLines'));
    }

    public function export(Request $request)
    {
        $rows = DB::table('mtp')
            ->join('colors', function ($join) {
                $join->on('mtp.Line', '=', 'colors.name')->where('colors.cate', '=', 'Subcon')->where('colors.is_active', '=', 1);
            })
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->leftJoin('subcon_contracts', 'mtp.id', '=', 'subcon_contracts.mtp_id')
            ->when($request->filled('line'), fn ($q) => $q->where('mtp.Line', $request->input('line')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->input('search'));
                $q->where(function ($query) use ($search) {
                    $query->where('mtp.CU', 'like', "%{$search}%")
                        ->orWhere('ocs.SNo', 'like', "%{$search}%")
                        ->orWhere('ocs.ONum', 'like', "%{$search}%")
                        ->orWhere('ocs.Customer', 'like', "%{$search}%");
                });
            })
            ->select('mtp.Line as subcon_line', 'mtp.CU as cs_no', 'ocs.SNo as style', 'ocs.ONum as po', 'ocs.Customer as customer',
                'mtp.Qty_dis as qty', 'mtp.SoTK as masterplan_sotk', 'mtp.ShipDate2 as masterplan_ship_date', 'subcon_contracts.*')
            ->orderBy('mtp.Line')->orderBy('mtp.CU')->get();
        $columns = [
            'subcon_line' => 'Subcon / Line', 'cs_no' => 'CS#', 'style' => 'Style', 'po' => 'PO#', 'customer' => 'Customer', 'qty' => 'Qty',
            'unit_price' => 'Unit Price', 'total_amount' => 'Total Amount', 'contract_no' => 'Số Hợp Đồng', 'contract_date' => 'Ngày Hợp Đồng',
            'appendix_no' => 'Số Phụ lục HĐ', 'appendix_date' => 'Ngày phụ lục', 'tax_code' => 'MST', 'processing_stage' => 'Công đoạn gia công',
            'customs_doc_no' => 'Số KLKX', 'customs_doc_date' => 'Ngày KLKX', 'form_12' => 'Mẫu 12', 'form_12_approved_date' => 'Ngày duyệt mẫu 12',
            'form_18a' => 'Mẫu 18A', 'form_18a_received_date' => 'Ngày nhận mẫu 18A', 'out_date' => 'OUT DATE', 'truck_plate' => 'TRUCK PLATE',
            'in_date' => 'IN DATE', 'invoice_no' => 'Invoice', 'invoice_date' => 'INV Date', 'actual_received_qty' => 'SL THUC NHAN',
            'payment_date' => 'NGAY THANH TOAN', 'masterplan_sotk' => 'STK XUAT', 'masterplan_ship_date' => 'NGAY TK',
            'notes' => 'Ghi chú', 'highlight_color' => 'Highlight',
        ];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Subcon Contract');
        $columnIndex = 1;
        foreach ($columns as $label) $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).'1', $label);
        $rowIndex = 2;
        foreach ($rows as $row) {
            $columnIndex = 1;
            foreach ($columns as $key => $label) {
                $value = match ($key) {
                    'total_amount' => (float) ($row->qty ?? 0) * (float) ($row->unit_price ?? 0),
                    'highlight_color' => ($row->highlight_color ?? '') === 'yellow' ? 'Đang làm (vàng)' : ((($row->highlight_color ?? '') === 'green') ? 'Hoàn thành (xanh)' : ''),
                    default => $row->{$key} ?? '',
                };
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).$rowIndex, $value);
            }
            $rowIndex++;
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $path = tempnam(sys_get_temp_dir(), 'subcon-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'subcon-contracts.xlsx')->deleteFileAfterSend(true);
    }

    public function update(Request $request, int $mtpId)
    {
        $this->authorizeEditing($request);

        $plan = DB::table('mtp')->where('id', $mtpId)->first();
        abort_unless($plan && DB::table('colors')->where('name', $plan->Line)->where('cate', 'Subcon')->where('is_active', 1)->exists(), 404);

        $rules = [
            'unit_price' => 'nullable|numeric|min:0|max:9999999999999.99',
            'contract_no' => 'nullable|string|max:191', 'contract_date' => 'nullable|date',
            'appendix_no' => 'nullable|string|max:191', 'appendix_date' => 'nullable|date', 'tax_code' => 'nullable|string|max:50',
            'processing_stage' => 'nullable|string|max:191',
            'customs_doc_no' => 'nullable|string|max:191', 'customs_doc_date' => 'nullable|date',
            'form_12' => 'nullable|string|max:191', 'form_12_approved_date' => 'nullable|date',
            'form_18a' => 'nullable|string|max:191', 'form_18a_received_date' => 'nullable|date',
            'out_date' => 'nullable|date', 'truck_plate' => 'nullable|string|max:50', 'in_date' => 'nullable|date',
            'invoice_no' => 'nullable|string|max:191', 'invoice_date' => 'nullable|date',
            'actual_received_qty' => 'nullable|numeric|min:0', 'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'highlight_color' => 'nullable|in:yellow,green',
        ];
        $rules = $this->editableRulesForRole($request, $rules);
        $data = $request->validate($rules);
        $existing = DB::table('subcon_contracts')->where('mtp_id', $mtpId)->exists();
        $data['updated_at'] = now();
        if ($existing) DB::table('subcon_contracts')->where('mtp_id', $mtpId)->update($data);
        else DB::table('subcon_contracts')->insert($data + ['mtp_id' => $mtpId, 'created_at' => now()]);

        return redirect()->route('admin.subcon-contracts.index', $request->only('line', 'search'))->with('success', 'Subcon Contract row saved.');
    }

    public function updateAll(Request $request)
    {
        $this->authorizeEditing($request);

        $rows = $request->validate(['contracts' => 'required|array', 'contracts.*' => 'required|array'])['contracts'];
        $rules = [
            'unit_price' => 'nullable|numeric|min:0|max:9999999999999.99', 'contract_no' => 'nullable|string|max:191', 'contract_date' => 'nullable|date',
            'appendix_no' => 'nullable|string|max:191', 'appendix_date' => 'nullable|date', 'tax_code' => 'nullable|string|max:50',
            'processing_stage' => 'nullable|string|max:191',
            'customs_doc_no' => 'nullable|string|max:191', 'customs_doc_date' => 'nullable|date', 'form_12' => 'nullable|string|max:191',
            'form_12_approved_date' => 'nullable|date', 'form_18a' => 'nullable|string|max:191', 'form_18a_received_date' => 'nullable|date',
            'out_date' => 'nullable|date', 'truck_plate' => 'nullable|string|max:50', 'in_date' => 'nullable|date',
            'invoice_no' => 'nullable|string|max:191', 'invoice_date' => 'nullable|date',
            'actual_received_qty' => 'nullable|numeric|min:0', 'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'highlight_color' => 'nullable|in:yellow,green',
        ];
        $rules = $this->editableRulesForRole($request, $rules);
        $validated = [];
        foreach ($rows as $mtpId => $row) {
            $validated[$mtpId] = Validator::make($row, $rules)->validate();
            $plan = DB::table('mtp')->where('id', $mtpId)->first();
            abort_unless($plan && DB::table('colors')->where('name', $plan->Line)->where('cate', 'Subcon')->where('is_active', 1)->exists(), 404);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated as $mtpId => $data) {
                $existing = DB::table('subcon_contracts')->where('mtp_id', $mtpId)->exists();
                $data['updated_at'] = now();
                if ($existing) DB::table('subcon_contracts')->where('mtp_id', $mtpId)->update($data);
                else DB::table('subcon_contracts')->insert($data + ['mtp_id' => $mtpId, 'created_at' => now()]);
            }
        });

        return redirect()->route('admin.subcon-contracts.index', $request->only('line', 'search'))->with('success', 'All Subcon Contract rows saved.');
    }

    private function editableRulesForRole(Request $request, array $rules): array
    {
        $fields = match ($request->user()->role) {
            'admin' => null,
            'ppic' => self::PPIC_EDITABLE_FIELDS,
            'accountant' => self::ACCOUNTANT_EDITABLE_FIELDS,
            'imex' => self::IMEX_EDITABLE_FIELDS,
            default => [],
        };

        return $fields === null ? $rules : array_intersect_key($rules, array_flip($fields));
    }

    private function authorizeEditing(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'ppic', 'accountant', 'imex'], true), 403);
    }
}
