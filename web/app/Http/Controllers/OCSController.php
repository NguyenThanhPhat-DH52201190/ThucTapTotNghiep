<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OCSController extends Controller
{
    public function index(Request $request)
    {
        $orders = DB::table('ocs')
        ->when($request->filled('cs'), function ($query) use ($request) {
            $query->where('CS', 'like', '%' . $request->cs . '%');
        })
        ->when($request->filled('customer'), function ($query) use ($request) {
            $query->where('Customer', 'like', '%' . $request->customer . '%');
        })
        ->when($request->filled('sname'), function ($query) use ($request) {
            $query->where('Sname', 'like', '%' . $request->sname . '%');
        })
        ->orderBy('CS', 'asc')
        ->get();
        return view('admin.ocs.ordercutsheet', compact('orders'));
    }

    public function create()
    {
        return view('admin.ocs.addocs');
    }

    public function store(Request $request)
    {
        $request->validate([
            'CS' => 'required',
            'CsDate' => 'required|date',
            'SNo' => 'required',
            'Sname' => 'required',
            'Customer' => 'required',
            'Color' => 'required',
            'ONum' => 'required',
            'CMT' => 'nullable|decimal:2|min:0',   // ✅ thêm
            'Qty' => 'required|integer|min:0'
        ]);

        DB::table('ocs')->insert([
            'CS' => $request->CS,
            'CsDate' => $request->CsDate,
            'SNo' => $request->SNo,
            'Sname' => $request->Sname,
            'Customer' => $request->Customer,
            'Color' => $request->Color,
            'ONum' => $request->ONum,
            'CMT' => $request->CMT,   // ✅ thêm
            'Qty' => $request->Qty
        ]);

        return redirect()->route('admin.ocs.index')
            ->with('success', 'Order added successfully');
    }

    public function edit(string $id)
    {
        $order = DB::table('ocs')->where('id', $id)->first();
        return view('admin.ocs.editocs', compact('order'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'CS' => 'required',
            'CsDate' => 'required|date',
            'SNo' => 'required',
            'Sname' => 'required',
            'Customer' => 'required',
            'Color' => 'required',
            'ONum' => 'required',
            'CMT' => 'nullable|decimal:2|min:0',
            'Qty' => 'required|integer|min:0'
        ]);

        DB::table('ocs')->where('id', $id)->update([
            'CS' => $request->CS,
            'CsDate' => $request->CsDate,
            'SNo' => $request->SNo,
            'Sname' => $request->Sname,
            'Customer' => $request->Customer,
            'Color' => $request->Color,
            'ONum' => $request->ONum,
            'CMT' => $request->CMT,   
            'Qty' => $request->Qty
        ]);

        return redirect()->route('admin.ocs.index')
            ->with('success', 'Order updated successfully');
    }

    public function destroy(string $id)
    {
        DB::table('ocs')->where('id', $id)->delete();

        return redirect()->route('admin.ocs.index')
            ->with('success', 'Deleted successfully');
    }
}