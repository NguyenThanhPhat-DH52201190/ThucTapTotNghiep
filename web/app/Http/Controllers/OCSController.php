<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OCSController extends Controller
{
    private function getOrders(Request $request): Collection
    {
        return DB::table('ocs')
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
    }

    public function index(Request $request)
    {
        $orders = $this->getOrders($request);
        return view('admin.ocs.ordercutsheet', compact('orders'));
    }

    public function export(Request $request)
    {
        $orders = $this->getOrders($request);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('OCS');

        $headers = ['CS', 'ONum', 'SNo', 'SName', 'Customer', 'CsDate', 'CMT', 'Color', 'Qty'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $header);
        }

        $rowIndex = 2;
        foreach ($orders as $item) {
            $sheet->setCellValue('A' . $rowIndex, $item->CS ?? '');
            $sheet->setCellValue('B' . $rowIndex, $item->ONum ?? '');
            $sheet->setCellValue('C' . $rowIndex, $item->SNo ?? '');
            $sheet->setCellValue('D' . $rowIndex, $item->Sname ?? '');
            $sheet->setCellValue('E' . $rowIndex, $item->Customer ?? '');
            $sheet->setCellValue('F' . $rowIndex, $item->CsDate ?? '');
            $sheet->setCellValue('G' . $rowIndex, $item->CMT ?? '');
            $sheet->setCellValue('H' . $rowIndex, $item->Color ?? '');
            $sheet->setCellValue('I' . $rowIndex, $item->Qty ?? '');
            $rowIndex++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'order-cutsheet-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function create()
    {
        return view('admin.ocs.addocs');
    }

    public function store(Request $request)
    {
        $request->validate([
            'CS' => 'required|unique:ocs,CS',
            'CsDate' => 'required|date',
            'SNo' => 'required',
            'Sname' => 'required',
            'Customer' => 'required',
            'Color' => 'required',
            'ONum' => 'required',
            'CMT' => 'nullable|numeric|min:0',
            'Qty' => 'required|integer|min:0'
        ], [
            'CS.unique' => 'CS da ton tai, vui long nhap CS khac.',
        ]);

        DB::table('ocs')->insert([
            'CS' => $request->CS,
            'CsDate' => $request->CsDate,
            'SNo' => $request->SNo,
            'Sname' => $request->Sname,
            'Customer' => $request->Customer,
            'Color' => $request->Color,
            'ONum' => $request->ONum,
            'CMT' => $request->CMT,   // added
            'Qty' => $request->Qty,
            'created_at' => now(),
            'updated_at' => now(),
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
        $currentOrder = DB::table('ocs')->where('id', $id)->first();

        if (!$currentOrder) {
            return redirect()->route('admin.ocs.index')
                ->with('error', 'Data not found');
        }

        $request->validate([
            'CS' => 'required|unique:ocs,CS,' . $id,
            'CsDate' => 'required|date',
            'SNo' => 'required',
            'Sname' => 'required',
            'Customer' => 'required',
            'Color' => 'required',
            'ONum' => 'required',
            'CMT' => 'nullable|numeric|min:0',
            'Qty' => 'required|integer|min:0'
        ], [
            'CS.unique' => 'CS already exists, please enter another CS.',
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
            'Qty' => $request->Qty,
            'updated_at' => now(),
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