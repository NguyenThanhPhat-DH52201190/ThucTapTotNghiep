<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ManualSubconContractController extends Controller
{
    private const IDENTITY_FIELDS = ['subcon_line', 'cs_no', 'style', 'po', 'customer', 'qty'];
    private const PPIC_FIELDS = [
        'unit_price', 'contract_no', 'contract_date', 'appendix_no', 'appendix_date', 'tax_code', 'processing_stage',
        'out_date', 'truck_plate', 'in_date', 'invoice_no', 'invoice_date', 'actual_received_qty', 'notes',
    ];
    private const ACCOUNTANT_FIELDS = ['payment_date', 'notes'];
    private const IMEX_FIELDS = [
        'customs_doc_no', 'customs_doc_date', 'form_12', 'form_12_approved_date', 'form_18a', 'form_18a_received_date',
        'customs_declaration_date', 'highlight_color', 'notes',
    ];

    private const EXPORT_COLUMNS = [
        'subcon_line' => 'Subcon / Line', 'cs_no' => 'CS#', 'style' => 'Style', 'po' => 'PO#', 'customer' => 'Customer', 'qty' => 'Qty',
        'unit_price' => 'Unit Price', 'total_amount' => 'Total Amount', 'contract_no' => 'Số Hợp Đồng', 'contract_date' => 'Ngày Hợp Đồng',
        'appendix_no' => 'Số Phụ lục HĐ', 'appendix_date' => 'Ngày phụ lục', 'tax_code' => 'MST', 'processing_stage' => 'Công đoạn gia công',
        'customs_doc_no' => 'Số KLKX', 'customs_doc_date' => 'Ngày KLKX', 'form_12' => 'Mẫu 12', 'form_12_approved_date' => 'Ngày duyệt mẫu 12',
        'form_18a' => 'Mẫu 18A', 'form_18a_received_date' => 'Ngày nhận mẫu 18A', 'out_date' => 'OUT DATE', 'truck_plate' => 'TRUCK PLATE',
        'in_date' => 'IN DATE', 'invoice_no' => 'Invoice', 'invoice_date' => 'INV Date', 'actual_received_qty' => 'SL THUC NHAN',
        'payment_date' => 'NGAY THANH TOAN', 'export_bank_account' => 'STK XUAT', 'customs_declaration_date' => 'NGAY TK',
        'notes' => 'Ghi chú', 'highlight_color' => 'Highlight',
    ];

    public function index(Request $request)
    {
        $contracts = $this->filteredRows($request)
            ->orderByRaw("CASE WHEN out_date IS NULL THEN 0 WHEN truck_plate IS NULL OR TRIM(truck_plate) = '' THEN 1 ELSE 2 END")
            ->orderBy('out_date')->orderBy('subcon_line')->orderBy('cs_no')->get()
            ->map(function ($row) {
                $row->masterplan_id = $row->id;
                $row->CU = $row->cs_no;
                $row->Line = $row->subcon_line;
                $row->Qty_dis = $row->qty;
                $row->masterplan_sotk = $row->export_bank_account;
                return $row;
            });
        $subconLines = DB::table('manual_subcon_contracts')->whereNotNull('subcon_line')->distinct()->orderBy('subcon_line')->pluck('subcon_line');

        return view('admin.subcon-contracts.manual', compact('contracts', 'subconLines'));
    }

    public function store(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'ppic'], true), 403);
        $count = $request->validate(['count' => 'required|integer|min:1|max:100'])['count'];
        $now = now();
        $rows = array_fill(0, $count, ['created_at' => $now, 'updated_at' => $now]);
        DB::table('manual_subcon_contracts')->insert($rows);

        return redirect()->route('admin.subcon-contracts-manual.index')->with('success', $count.' manual Subcon Contract row(s) added.');
    }

    public function updateAll(Request $request)
    {
        $this->authorizeEditing($request);
        $rows = $request->validate(['contracts' => 'required|array', 'contracts.*' => 'required|array'])['contracts'];
        $rules = [
            'subcon_line' => 'nullable|string|max:191', 'cs_no' => 'nullable|string|max:100', 'style' => 'nullable|string|max:100',
            'po' => 'nullable|string|max:100', 'customer' => 'nullable|string|max:191', 'qty' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0|max:9999999999999.99', 'contract_no' => 'nullable|string|max:191', 'contract_date' => 'nullable|date',
            'appendix_no' => 'nullable|string|max:191', 'appendix_date' => 'nullable|date', 'tax_code' => 'nullable|string|max:50',
            'processing_stage' => 'nullable|string|max:191', 'customs_doc_no' => 'nullable|string|max:191', 'customs_doc_date' => 'nullable|date',
            'form_12' => 'nullable|string|max:191', 'form_12_approved_date' => 'nullable|date', 'form_18a' => 'nullable|string|max:191',
            'form_18a_received_date' => 'nullable|date', 'out_date' => 'nullable|date', 'truck_plate' => 'nullable|string|max:50',
            'in_date' => 'nullable|date', 'invoice_no' => 'nullable|string|max:191', 'invoice_date' => 'nullable|date',
            'actual_received_qty' => 'nullable|numeric|min:0', 'payment_date' => 'nullable|date',
            'export_bank_account' => 'nullable|string|max:191', 'customs_declaration_date' => 'nullable|date',
            'highlight_color' => 'nullable|in:yellow,green', 'notes' => 'nullable|string|max:5000',
        ];
        $rules = $this->editableRulesForRole($request, $rules);
        $validated = [];
        foreach ($rows as $id => $row) {
            $validated[$id] = Validator::make($row, $rules)->validate();
            abort_unless(DB::table('manual_subcon_contracts')->where('id', $id)->exists(), 404);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated as $id => $data) {
                $data['updated_at'] = now();
                DB::table('manual_subcon_contracts')->where('id', $id)->update($data);
            }
        });

        return redirect()->route('admin.subcon-contracts-manual.index', $request->only('line', 'search'))->with('success', 'All manual Subcon Contract rows saved.');
    }

    public function export(Request $request)
    {
        $rows = $this->filteredRows($request)->orderBy('subcon_line')->orderBy('cs_no')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Manual Subcon');
        $columnIndex = 1;
        foreach (self::EXPORT_COLUMNS as $key => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).'1', $label);
        }
        $rowIndex = 2;
        foreach ($rows as $row) {
            $columnIndex = 1;
            foreach (self::EXPORT_COLUMNS as $key => $label) {
                $value = match ($key) {
                    'total_amount' => (float) ($row->qty ?? 0) * (float) ($row->unit_price ?? 0),
                    'highlight_color' => $row->highlight_color === 'yellow' ? 'Đang làm (vàng)' : ($row->highlight_color === 'green' ? 'Hoàn thành (xanh)' : ''),
                    default => $row->{$key} ?? '',
                };
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex++).$rowIndex, $value);
            }
            $rowIndex++;
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $path = tempnam(sys_get_temp_dir(), 'manual-subcon-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'manual-subcon-contracts.xlsx')->deleteFileAfterSend(true);
    }

    private function filteredRows(Request $request)
    {
        return DB::table('manual_subcon_contracts')
            ->when($request->filled('line'), fn ($query) => $query->where('subcon_line', $request->input('line')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));
                $query->where(function ($subquery) use ($search) {
                    foreach (['cs_no', 'style', 'po', 'customer'] as $field) $subquery->orWhere($field, 'like', "%{$search}%");
                });
            });
    }

    private function editableRulesForRole(Request $request, array $rules): array
    {
        $fields = match ($request->user()->role) {
            'admin' => null,
            'ppic' => [...self::IDENTITY_FIELDS, ...self::PPIC_FIELDS],
            'accountant' => self::ACCOUNTANT_FIELDS,
            'imex' => self::IMEX_FIELDS,
            default => [],
        };

        return $fields === null ? $rules : array_intersect_key($rules, array_flip($fields));
    }

    private function authorizeEditing(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'ppic', 'accountant', 'imex'], true), 403);
    }
}
