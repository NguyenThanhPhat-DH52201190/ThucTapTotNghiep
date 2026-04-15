<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $holidays = DB::table('holidays')->orderBy('holiday')->get();
        return view('admin.masterplan.holiday', compact('holidays'));
    }

    public function export()
    {
        $holidays = DB::table('holidays')->orderBy('holiday')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Holidays');

        $headers = ['Date', 'Name'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $header);
        }

        $rowIndex = 2;
        foreach ($holidays as $holiday) {
            $sheet->setCellValue('A' . $rowIndex, $holiday->holiday ?? '');
            $sheet->setCellValue('B' . $rowIndex, $holiday->name ?? '');
            $rowIndex++;
        }

        foreach (range('A', 'B') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'holidays-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.masterplan.holiday_create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $request->validate([
            'holiday' => 'required|date|unique:holidays,holiday',
            'name' => 'nullable|string|max:255'
        ]);

        try {
            DB::table('holidays')->insert([
                'holiday' => $request->holiday,
                'name' => $request->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.holidays.index')
                ->with('success', 'Added successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to create holiday record', [
                'message' => $e->getMessage(),
                'input' => $request->except(['_token']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to save the holiday. Please check your input and try again.');
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $holiday = DB::table('holidays')->where('id', $id)->first();
        return view('admin.masterplan.holiday_edit', compact('holiday'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'holiday' => 'required|date|unique:holidays,holiday,' . $id,
            'name' => 'nullable|string|max:255'
        ]);

        try {
            DB::table('holidays')->where('id', $id)->update([
                'holiday' => $request->holiday,
                'name' => $request->name,
                'updated_at' => now(),
            ]);

            cache()->forget('holidays');

            return redirect()->route('admin.holidays.index')
                ->with('success', 'Updated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to update holiday record', [
                'message' => $e->getMessage(),
                'id' => $id,
                'input' => $request->except(['_token', '_method']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update the holiday. Please check your input and try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $deleted = DB::table('holidays')->where('id', $id)->delete();

            if (!$deleted) {
                return redirect()->back()->with('error', 'Record not found.');
            }

            cache()->forget('holidays');

            return redirect()->back()->with('success', 'Deleted successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to delete holiday record', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return redirect()->back()->with('error', 'Unable to delete the holiday. Please try again.');
        }
    }
}
