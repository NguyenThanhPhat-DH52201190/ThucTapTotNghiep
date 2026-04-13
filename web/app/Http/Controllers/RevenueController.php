<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    // 📌 Danh sách
    public function index(Request $request)
    {
        $revenues = DB::table('revenue')
            ->Join('ocs', 'revenue.CS', '=', 'ocs.CS')
            ->when($request->filled('cs'), function ($query) use ($request) {
                $query->where('revenue.CS', 'like', '%' . $request->cs . '%');
            })
            ->select(
                'revenue.id',
                'revenue.CS',
                'revenue.planout',
                'revenue.actualout',
                'revenue.sewingmp',
                'revenue.workhrs',
                'ocs.CMT as cmp'

            )
            ->get();

        return view('admin.revenue.revenue', compact('revenues'));
    }

    // 📌 Form add
    public function create()
    {
        $ocs = DB::table('ocs')->get();
        return view('admin.revenue.addrevenue', compact('ocs'));
    }

    // 📌 Lưu
    public function store(Request $request)
    {
        $request->validate([
            'CS' => 'required',
            'planout' => 'required|numeric',
            'actualout' => 'required|numeric',
            'sewingmp' => 'required|numeric',
            'workhrs' => 'required|numeric',
            'cmp' => 'required|numeric|min:0',
        ]);

        DB::table('revenue')->insert([
            'CS' => $request->CS,
            'planout' => $request->planout,
            'actualout' => $request->actualout,
            'sewingmp' => $request->sewingmp,
            'workhrs' => $request->workhrs,
            'cmp' => $request->cmp,
        ]);

        return redirect()
            ->route('admin.revenue.index')
            ->with('success', 'Added successfully');
    }

    public function edit(string $id)
    {
        $revenue = DB::table('revenue')
        ->leftJoin('ocs', 'revenue.CS', '=', 'ocs.CS')
        ->select(
            'revenue.*',
            'ocs.CMT as cmp'
        )
        ->where('revenue.id', $id)
        ->first();

    if (!$revenue) {
        dd('Không tìm thấy dữ liệu');
    }

    return view('admin.revenue.editrevenue', compact('revenue'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'CS' => 'required',
            'planout' => 'required|numeric',
            'actualout' => 'required|numeric',
            'sewingmp' => 'required|numeric',
            'workhrs' => 'required|numeric',
            'cmp' => 'required|numeric|min:0',
        ]);

        DB::table('revenue')->where('id', $id)->update([
            'planout' => $request->planout,
            'actualout' => $request->actualout,
            'sewingmp' => $request->sewingmp,
            'workhrs' => $request->workhrs,
            'cmp' => $request->cmp,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.revenue.index')
            ->with('success', 'Updated successfully');
    }

    // 📌 Xoá (bonus cho bạn)
    public function destroy($id)
    {
        $revenue = DB::table('revenue')->where('id', $id)->first();

        if (!$revenue) {
            return redirect()->back()->with('error', 'Data not found');
        }

        DB::table('revenue')->where('id', $id)->delete();

        return redirect()->route('admin.revenue.index')
            ->with('success', 'Deleted successfully');
    }
}
