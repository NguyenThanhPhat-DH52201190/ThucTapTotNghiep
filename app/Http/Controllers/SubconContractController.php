<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubconContractController extends Controller
{
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
            ->select('mtp.id as mtp_id', 'mtp.CU', 'mtp.Line', 'mtp.Qty_dis', 'mtp.Norm_date', 'mtp.fabric_issue_date',
                'ocs.SNo as style', 'ocs.ONum as po', 'ocs.Customer as customer', 'ocs.Color as garment_color',
                'subcon_contracts.*')
            ->orderBy('mtp.Line')->orderBy('mtp.CU')->get();

        return view('admin.subcon-contracts.index', compact('contracts', 'subconLines'));
    }

    public function update(Request $request, int $mtpId)
    {
        $plan = DB::table('mtp')->where('id', $mtpId)->first();
        abort_unless($plan && DB::table('colors')->where('name', $plan->Line)->where('cate', 'Subcon')->where('is_active', 1)->exists(), 404);

        $data = $request->validate([
            'code' => 'nullable|string|max:100', 'unit_price' => 'nullable|numeric|min:0|max:9999999999999.99',
            'contract_no' => 'nullable|string|max:191', 'contract_date' => 'nullable|date',
            'appendix_no' => 'nullable|string|max:191', 'appendix_date' => 'nullable|date', 'tax_code' => 'nullable|string|max:50',
            'customs_doc_no' => 'nullable|string|max:191', 'customs_doc_date' => 'nullable|date',
            'form_12' => 'nullable|string|max:191', 'form_12_approved_date' => 'nullable|date',
            'form_18a' => 'nullable|string|max:191', 'form_18a_received_date' => 'nullable|date',
            'out_date' => 'nullable|date', 'truck_plate' => 'nullable|string|max:50', 'in_date' => 'nullable|date',
            'truck_plate_2' => 'nullable|string|max:50', 'invoice_no' => 'nullable|string|max:191', 'invoice_date' => 'nullable|date',
            'actual_received_qty' => 'nullable|numeric|min:0', 'payment_date' => 'nullable|date',
            'export_bank_account' => 'nullable|string|max:191', 'customs_declaration_date' => 'nullable|date', 'notes' => 'nullable|string|max:5000',
        ]);
        $existing = DB::table('subcon_contracts')->where('mtp_id', $mtpId)->exists();
        $data['updated_at'] = now();
        if ($existing) DB::table('subcon_contracts')->where('mtp_id', $mtpId)->update($data);
        else DB::table('subcon_contracts')->insert($data + ['mtp_id' => $mtpId, 'created_at' => now()]);

        return redirect()->route('admin.subcon-contracts.index', $request->only('line', 'search'))->with('success', 'Subcon Contract row saved.');
    }
}
