<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RevenueController extends Controller
{
    private function getRevenues(Request $request): Collection
    {
        return DB::table('revenue')
            ->join('ocs', 'revenue.CS', '=', 'ocs.CS')
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
    }

    // 📌 Danh sách
    public function index(Request $request)
    {
        $revenues = $this->getRevenues($request);

        return view('admin.revenue.revenue', compact('revenues'));
    }

    public function export(Request $request)
    {
        $revenues = $this->getRevenues($request);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Revenue');

        $headers = ['CS', 'planout', 'actualout', 'sewingmp', 'workhrs', 'cmp'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $header);
        }

        $rowIndex = 2;
        foreach ($revenues as $item) {
            $sheet->setCellValue('A' . $rowIndex, $item->CS ?? '');
            $sheet->setCellValue('B' . $rowIndex, $item->planout ?? '');
            $sheet->setCellValue('C' . $rowIndex, $item->actualout ?? '');
            $sheet->setCellValue('D' . $rowIndex, $item->sewingmp ?? '');
            $sheet->setCellValue('E' . $rowIndex, $item->workhrs ?? '');
            $sheet->setCellValue('F' . $rowIndex, $item->cmp ?? '');
            $rowIndex++;
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'revenue-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
        ]);

        DB::table('revenue')->insert([
            'CS' => $request->CS,
            'planout' => $request->planout,
            'actualout' => $request->actualout,
            'sewingmp' => $request->sewingmp,
            'workhrs' => $request->workhrs,
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
            'planout' => 'required|numeric',
            'actualout' => 'required|numeric',
            'sewingmp' => 'required|numeric',
            'workhrs' => 'required|numeric',
        ]);

        DB::table('revenue')->where('id', $id)->update([
            'planout' => $request->planout,
            'actualout' => $request->actualout,
            'sewingmp' => $request->sewingmp,
            'workhrs' => $request->workhrs,
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
