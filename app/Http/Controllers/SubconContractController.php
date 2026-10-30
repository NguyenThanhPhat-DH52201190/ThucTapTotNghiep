<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubconContractController extends Controller
{
    private const PPIC_EDITABLE_FIELDS = ['unit_price', 'contract_no', 'contract_date', 'appendix_no', 'appendix_date', 'tax_code', 'out_date', 'truck_plate', 'in_date', 'truck_plate_2', 'invoice_no', 'invoice_date', 'actual_received_qty', 'notes'];
    private const IE_EDITABLE_FIELDS = ['customs_doc_no', 'customs_doc_date', 'form_12', 'form_12_approved_date', 'form_18a', 'form_18a_received_date', 'notes'];
    private const ACCOUNTANT_EDITABLE_FIELDS = ['payment_date', 'notes'];
    private const LIMITED_EDITABLE_FIELDS = ['notes'];

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
            ->select('mtp.id as masterplan_id', 'mtp.CU', 'mtp.Line', 'mtp.LineColor as line_color', 'mtp.Qty_dis', 'mtp.Norm_date', 'mtp.fabric_issue_date', 'mtp.SoTK as masterplan_sotk',
                'ocs.SNo as style', 'ocs.ONum as po', 'ocs.Customer as customer', 'ocs.Color as garment_color',
                'subcon_contracts.*')
            ->orderBy('mtp.Line')->orderBy('mtp.CU')->get();

        return view('admin.subcon-contracts.index', compact('contracts', 'subconLines'));
    }

    public function update(Request $request, int $mtpId)
    {
        $plan = DB::table('mtp')->where('id', $mtpId)->first();
        abort_unless($plan && DB::table('colors')->where('name', $plan->Line)->where('cate', 'Subcon')->where('is_active', 1)->exists(), 404);

        $rules = [
            'unit_price' => 'nullable|numeric|min:0|max:9999999999999.99',
            'contract_no' => 'nullable|string|max:191', 'contract_date' => 'nullable|date',
            'appendix_no' => 'nullable|string|max:191', 'appendix_date' => 'nullable|date', 'tax_code' => 'nullable|string|max:50',
            'customs_doc_no' => 'nullable|string|max:191', 'customs_doc_date' => 'nullable|date',
            'form_12' => 'nullable|string|max:191', 'form_12_approved_date' => 'nullable|date',
            'form_18a' => 'nullable|string|max:191', 'form_18a_received_date' => 'nullable|date',
            'out_date' => 'nullable|date', 'truck_plate' => 'nullable|string|max:50', 'in_date' => 'nullable|date',
            'truck_plate_2' => 'nullable|string|max:50', 'invoice_no' => 'nullable|string|max:191', 'invoice_date' => 'nullable|date',
            'actual_received_qty' => 'nullable|numeric|min:0', 'payment_date' => 'nullable|date',
            'customs_declaration_date' => 'nullable|date', 'notes' => 'nullable|string|max:5000',
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
        $rows = $request->validate(['contracts' => 'required|array', 'contracts.*' => 'required|array'])['contracts'];
        $rules = [
            'unit_price' => 'nullable|numeric|min:0|max:9999999999999.99', 'contract_no' => 'nullable|string|max:191', 'contract_date' => 'nullable|date',
            'appendix_no' => 'nullable|string|max:191', 'appendix_date' => 'nullable|date', 'tax_code' => 'nullable|string|max:50',
            'customs_doc_no' => 'nullable|string|max:191', 'customs_doc_date' => 'nullable|date', 'form_12' => 'nullable|string|max:191',
            'form_12_approved_date' => 'nullable|date', 'form_18a' => 'nullable|string|max:191', 'form_18a_received_date' => 'nullable|date',
            'out_date' => 'nullable|date', 'truck_plate' => 'nullable|string|max:50', 'in_date' => 'nullable|date',
            'truck_plate_2' => 'nullable|string|max:50', 'invoice_no' => 'nullable|string|max:191', 'invoice_date' => 'nullable|date',
            'actual_received_qty' => 'nullable|numeric|min:0', 'payment_date' => 'nullable|date', 'customs_declaration_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
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
            'ie' => self::IE_EDITABLE_FIELDS,
            'accountant' => self::ACCOUNTANT_EDITABLE_FIELDS,
            default => self::LIMITED_EDITABLE_FIELDS,
        };

        return $fields === null ? $rules : array_intersect_key($rules, array_flip($fields));
    }
}
