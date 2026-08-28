<?php

namespace App\Http\Controllers;

use App\Services\OrderCostSnapshotService;
use App\Jobs\CreateRequisitionForCutsheet;
use App\Services\RequisitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\OCSImport;

class OCSController extends Controller
{
    private function isLocked(object $order): bool
    {
        return in_array($order->status, ['released', 'in_production', 'completed', 'closed'], true);
    }

    private function orderRules(?int $id = null): array
    {
        return [
            'CS' => 'required|unique:ocs,CS' . ($id ? ',' . $id : ''),
            'CsDate' => 'required|date', 'SNo' => 'required', 'Sname' => 'required',
            'Customer' => 'required', 'customer_id' => 'nullable|exists:customer_info,id', 'Color' => 'required', 'ONum' => 'required',
            'CMT' => 'nullable|numeric|min:0', 'Qty' => 'required|integer|min:1',
            'order_type' => 'required|in:cmt,fob', 'material_ownership' => 'required|in:factory,customer',
            'unit_price' => 'nullable|numeric|min:0',
            'bom_header_id' => 'nullable|exists:bom_headers,id',
            'expected_ship_date' => 'nullable|date', 'priority' => 'nullable|in:low,medium,high,urgent',
            'sizes' => 'required|array|min:1',
            'sizes.*.size_name' => 'required|string|max:50|distinct',
            'sizes.*.quantity' => 'required|integer|min:0',
        ];
    }

    private function validateSizeTotal(Request $request): void
    {
        $total = collect($request->input('sizes', []))->sum(fn ($size) => (int) ($size['quantity'] ?? 0));
        if ($total !== (int) $request->Qty) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sizes' => ['Tổng số lượng theo size phải bằng Qty của đơn hàng.'],
            ]);
        }
    }
    private function validateBomForOrder(Request $request): void
    {
        if (!$request->bom_header_id) return;
        $bom = DB::table('bom_headers')->where('id', $request->bom_header_id)->first();
        if (!$bom || $bom->status !== 'active') throw \Illuminate\Validation\ValidationException::withMessages(['bom_header_id' => ['Only an active BOM can be assigned.']]);
        if (trim((string) $bom->style_no) !== trim((string) $request->SNo)) throw \Illuminate\Validation\ValidationException::withMessages(['bom_header_id' => ['The BOM style must match the order style.']]);
    }
    private function getOrders(Request $request): Collection
    {
        return DB::table('ocs')
            ->leftJoin('bom_headers', 'ocs.bom_header_id', '=', 'bom_headers.id')
            ->when($request->filled('cs'), function ($query) use ($request) {
                $query->where('ocs.CS', 'like', '%' . $request->cs . '%');
            })
            ->when($request->filled('customer'), function ($query) use ($request) {
                $query->where('ocs.Customer', 'like', '%' . $request->customer . '%');
            })
            ->when($request->filled('sname'), function ($query) use ($request) {
                $query->where('ocs.Sname', 'like', '%' . $request->sname . '%');
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('ocs.status', $request->status);
            })
            ->select('ocs.*', 'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version')
            ->orderBy('ocs.CS', 'asc')
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
        $boms = DB::table('bom_headers')->where('status', 'active')->orderBy('style_no')->get();
        $customers = DB::table('customer_info')->orderBy('name')->get();
        return view('admin.ocs.addocs', compact('boms', 'customers'));
    }

    public function store(Request $request, RequisitionService $requisitions)
    {
        $request->validate($this->orderRules());
        $this->validateSizeTotal($request);
        $this->validateBomForOrder($request);
        try {
            DB::transaction(function () use ($request, $requisitions) {
                $orderId = DB::table('ocs')->insertGetId([
                    'CS' => $request->CS, 'CsDate' => $request->CsDate, 'SNo' => $request->SNo,
                    'Sname' => $request->Sname, 'Customer' => $request->Customer, 'customer_id' => $request->customer_id, 'Color' => $request->Color,
                    'ONum' => $request->ONum, 'CMT' => $request->CMT, 'Qty' => $request->Qty,
                    'order_type' => $request->order_type, 'material_ownership' => $request->material_ownership, 'unit_price' => $request->unit_price ?? 0,
                    'status' => 'pending', 'bom_header_id' => $request->bom_header_id,
                    'expected_ship_date' => $request->expected_ship_date, 'priority' => $request->priority ?? 'medium',
                    'order_notes' => $request->order_notes, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('order_sizes')->insert(collect($request->sizes)->map(fn ($size) => [
                    'cutsheet_id' => $orderId, 'size_name' => $size['size_name'], 'quantity' => $size['quantity'],
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
            });
            return redirect()->route('admin.ocs.index')->with('success', 'Order and size breakdown saved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to create OCS order', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Unable to save the order. Please try again.');
        }
        /*
        $request->validate([
            'CS' => 'required|unique:ocs,CS',
            'CsDate' => 'required|date',
            'SNo' => 'required',
            'Sname' => 'required',
            'Customer' => 'required',
            'Color' => 'required',
            'ONum' => 'required',
            'CMT' => 'nullable|numeric|min:0',
            'Qty' => 'required|integer|min:0',
            'status' => 'nullable|in:pending,confirmed,in_production,completed,cancelled',
            'bom_header_id' => 'nullable|exists:bom_headers,id',
            'expected_ship_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ], [
            'CS.unique' => 'CS already exists. Please enter a different CS.',
        ]);

        try {
            DB::table('ocs')->insert([
                'CS' => $request->CS,
                'CsDate' => $request->CsDate,
                'SNo' => $request->SNo,
                'Sname' => $request->Sname,
                'Customer' => $request->Customer,
                'Color' => $request->Color,
                'ONum' => $request->ONum,
                'CMT' => $request->CMT,
                'Qty' => $request->Qty,
                'status' => $request->status ?? 'pending',
                'bom_header_id' => $request->bom_header_id,
                'expected_ship_date' => $request->expected_ship_date,
                'priority' => $request->priority ?? 'medium',
                'order_notes' => $request->order_notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.ocs.index')
                ->with('success', 'Order added successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to create OCS order', [
                'message' => $e->getMessage(),
                'input' => $request->except(['_token']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to save the order. Please check your input and try again.');
        }*/
    }

    public function edit(string $id)
    {
        $order = DB::table('ocs')->where('id', $id)->first();
        if ($this->isLocked($order)) return redirect()->route('admin.ocs.index')->with('error', 'Orders in production or completed are locked.');
        $boms = DB::table('bom_headers')->where('status', 'active')->orderBy('style_no')->get();
        $customers = DB::table('customer_info')->orderBy('name')->get();
        $sizes = DB::table('order_sizes')->where('cutsheet_id', $id)->orderBy('id')->get();
        return view('admin.ocs.editocs', compact('order', 'boms', 'sizes', 'customers'));
    }

    public function update(Request $request, string $id, RequisitionService $requisitions)
    {
        $currentOrder = DB::table('ocs')->where('id', $id)->first();

        if (!$currentOrder) {
            return redirect()->route('admin.ocs.index')
                ->with('error', 'Record not found.');
        }

        if ($this->isLocked($currentOrder)) return back()->with('error', 'Orders in production or completed cannot be changed.');
        $request->validate($this->orderRules((int) $id));
        $this->validateSizeTotal($request);
        $this->validateBomForOrder($request);
        try {
            DB::transaction(function () use ($request, $id, $requisitions) {
                DB::table('ocs')->where('id', $id)->update([
                    'CS' => $request->CS, 'CsDate' => $request->CsDate, 'SNo' => $request->SNo, 'Sname' => $request->Sname,
                    'Customer' => $request->Customer, 'customer_id' => $request->customer_id, 'Color' => $request->Color, 'ONum' => $request->ONum, 'CMT' => $request->CMT,
                    'order_type' => $request->order_type, 'material_ownership' => $request->material_ownership, 'unit_price' => $request->unit_price ?? 0,
                    'Qty' => $request->Qty, 'bom_header_id' => $request->bom_header_id,
                    'expected_ship_date' => $request->expected_ship_date, 'priority' => $request->priority ?? 'medium',
                    'order_notes' => $request->order_notes, 'updated_at' => now(),
                ]);
                DB::table('order_sizes')->where('cutsheet_id', $id)->delete();
                DB::table('order_sizes')->insert(collect($request->sizes)->map(fn ($size) => [
                    'cutsheet_id' => $id, 'size_name' => $size['size_name'], 'quantity' => $size['quantity'],
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
            });
            return redirect()->route('admin.ocs.index')->with('success', 'Order and size breakdown updated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to update OCS order', ['message' => $e->getMessage(), 'id' => $id]);
            return back()->withInput()->with('error', 'Unable to update the order. Please try again.');
        }
        /*
        $request->validate([
            'CS' => 'required|unique:ocs,CS,' . $id,
            'CsDate' => 'required|date',
            'SNo' => 'required',
            'Sname' => 'required',
            'Customer' => 'required',
            'Color' => 'required',
            'ONum' => 'required',
            'CMT' => 'nullable|numeric|min:0',
            'Qty' => 'required|integer|min:0',
            'status' => 'nullable|in:pending,confirmed,in_production,completed,cancelled',
            'bom_header_id' => 'nullable|exists:bom_headers,id',
            'expected_ship_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ], [
            'CS.unique' => 'CS already exists, please enter another CS.',
        ]);

        try {
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
                'status' => $request->status ?? 'pending',
                'bom_header_id' => $request->bom_header_id,
                'expected_ship_date' => $request->expected_ship_date,
                'priority' => $request->priority ?? 'medium',
                'order_notes' => $request->order_notes,
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.ocs.index')
                ->with('success', 'Order updated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to update OCS order', [
                'message' => $e->getMessage(),
                'id' => $id,
                'input' => $request->except(['_token', '_method']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update the order. Please check your input and try again.');
        }*/
    }

    public function destroy(string $id)
    {
        $order = DB::table('ocs')->where('id', $id)->first();
        if (!$order) return redirect()->route('admin.ocs.index')->with('error', 'Record not found.');
        if ($this->isLocked($order)) return redirect()->route('admin.ocs.index')->with('error', 'Orders in production or completed cannot be deleted.');
        try {
            $deleted = DB::table('ocs')->where('id', $id)->delete();

            if (!$deleted) {
                return redirect()->route('admin.ocs.index')
                    ->with('error', 'Record not found.');
            }

            return redirect()->route('admin.ocs.index')
                ->with('success', 'Deleted successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to delete OCS order', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return redirect()->route('admin.ocs.index')
                ->with('error', 'Unable to delete the order. Please try again.');
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:2048'
        ]);

        try {
            Excel::import(new OCSImport, $request->file('file'));

            return back()->with('success', 'Excel import completed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Quick status update via AJAX
     */
    public function updateStatus(Request $request, $id, RequisitionService $requisitions, OrderCostSnapshotService $costings, \App\Services\AuditTrailService $audit)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,released,in_production,completed,closed,cancelled',
            'change_reason' => 'nullable|string|max:255',
        ]);

        $allowed = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['released', 'cancelled'],
            'released' => ['in_production', 'cancelled'],
            'in_production' => ['completed'],
            'completed' => ['closed'],
            'closed' => [],
            'cancelled' => [],
        ];
        try {
            DB::transaction(function () use ($request, $id, $requisitions, $costings, $audit) {
                $order = DB::table('ocs')->where('id', $id)->lockForUpdate()->first();
                if (!$order) abort(404);
                $allowed = [
                    'pending' => ['confirmed', 'cancelled'], 'confirmed' => ['released', 'cancelled'],
                    'released' => ['in_production', 'cancelled'], 'in_production' => ['completed'],
                    'completed' => ['closed'], 'closed' => [], 'cancelled' => [],
                ];
                if ($request->status !== $order->status && !in_array($request->status, $allowed[$order->status] ?? [], true)) {
                    throw new \RuntimeException("Cannot change status from '{$order->status}' to '{$request->status}'.");
                }
                if ($request->status === 'released') {
                    DB::table('ocs')->where('id', $id)->update(['requisition_job_status' => 'queued', 'requisition_job_error' => null, 'updated_at' => now()]);
                    CreateRequisitionForCutsheet::dispatch((int) $id)->afterCommit();
                }
                DB::table('ocs')->where('id', $id)->update(['status' => $request->status, 'updated_at' => now()]);
                if ($request->status !== $order->status) $audit->record('status_changed', 'order_cutsheet', (int) $id, $request->user()?->id, ['status' => $order->status], ['status' => $request->status], $request->change_reason ?: 'Workflow status transition');
                if ($request->status === 'closed') $costings->snapshot((int) $id);
            });
        } catch (\Throwable $e) {
            Log::warning('Order status transition rejected', ['order_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Order status updated to ' . $request->status);
    }
}
