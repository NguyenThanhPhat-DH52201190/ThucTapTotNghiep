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
        return in_array($order->status, ['confirmed', 'in_production', 'completed', 'released', 'closed'], true);
    }

    private function orderRules(?int $id = null): array
    {
        return [
            'CS' => 'required|unique:ocs,CS' . ($id ? ',' . $id : ''),
            'CsDate' => 'required|date', 'SNo' => 'required', 'Sname' => 'required',
            'Customer' => 'required', 'customer_id' => 'nullable|exists:customer_info,id', 'Color' => 'required', 'ONum' => 'required',
            'CMT' => 'nullable|numeric|min:0', 'Qty' => 'required|integer|min:1',
            'order_type' => 'required|in:cmt,fob', 'material_ownership' => 'required|in:factory,customer',
            'unit_price' => 'nullable|numeric|decimal:0,4|min:0',
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
        if (!$bom || $bom->status !== 'active' || ($bom->bom_kind ?? 'template') !== 'template') {
            throw \Illuminate\Validation\ValidationException::withMessages(['bom_header_id' => ['Only an active BOM template can be assigned.']]);
        }
        if (trim((string) $bom->style_no) !== trim((string) $request->SNo)) throw \Illuminate\Validation\ValidationException::withMessages(['bom_header_id' => ['The BOM style must match the order style.']]);
    }
    private function getOrders(Request $request): Collection
    {
        return DB::table('ocs')
            ->leftJoin('bom_headers', 'ocs.bom_header_id', '=', 'bom_headers.id')
            // Legacy cancelled orders stay archived and are not part of the new workflow.
            ->where('ocs.status', '!=', 'cancelled')
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
            ->select('ocs.*', 'bom_headers.style_no as bom_style', 'bom_headers.version as bom_version',
                'bom_headers.bom_kind', 'bom_headers.mapping_status')
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
        $boms = DB::table('bom_headers')->where('status', 'active')->where('bom_kind', 'template')->orderBy('style_no')->get();
        $customers = DB::table('customer_info')->orderBy('name')->get();
        return view('admin.ocs.addocs', compact('boms', 'customers'));
    }

    public function store(Request $request, RequisitionService $requisitions)
    {
        $request->validate($this->orderRules());
        $this->validateSizeTotal($request);
        $this->validateBomForOrder($request);
        try {
            [$orderId, $mappingStatus] = DB::transaction(function () use ($request, $requisitions) {
                $orderId = DB::table('ocs')->insertGetId([
                    'CS' => $request->CS, 'CsDate' => $request->CsDate, 'SNo' => $request->SNo,
                    'Sname' => $request->Sname, 'Customer' => $request->Customer, 'customer_id' => $request->customer_id, 'Color' => $request->Color,
                    'ONum' => $request->ONum, 'CMT' => $request->CMT, 'Qty' => $request->Qty,
                    'order_type' => $request->order_type, 'material_ownership' => $request->material_ownership, 'unit_price' => $request->unit_price ?? 0,
                    'status' => 'pending', 'bom_header_id' => null,
                    'expected_ship_date' => $request->expected_ship_date, 'priority' => $request->priority ?? 'medium',
                    'order_notes' => $request->order_notes, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('order_sizes')->insert(collect($request->sizes)->map(fn ($size) => [
                    'cutsheet_id' => $orderId, 'size_name' => $size['size_name'], 'quantity' => $size['quantity'],
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
                [, $mappingStatus] = $this->createOrderBom($orderId, $request->integer('bom_header_id') ?: null, $request->user()?->id);
                return [$orderId, $mappingStatus];
            });
            if ($mappingStatus === 'needs_mapping') {
                return redirect()->route('admin.ocs.bom-size-mapping', $orderId)
                    ->with('success', 'OCS created. Complete BOM size mapping before confirmation.');
            }
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
        if ($this->isLocked($order)) return redirect()->route('admin.ocs.index')->with('error', 'Confirmed, in-production, or completed orders are locked.');
        $boms = DB::table('bom_headers')->where('status', 'active')->where('bom_kind', 'template')->orderBy('style_no')->get();
        $assignedBom = $order->bom_header_id ? DB::table('bom_headers')->find($order->bom_header_id) : null;
        $order->selected_template_id = $assignedBom?->template_id ?: $order->bom_header_id;
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

        if ($this->isLocked($currentOrder)) return back()->with('error', 'Confirmed, in-production, or completed orders cannot be changed.');
        $request->validate($this->orderRules((int) $id));
        $this->validateSizeTotal($request);
        $this->validateBomForOrder($request);
        try {
            $mappingStatus = DB::transaction(function () use ($request, $id, $requisitions, $currentOrder) {
                DB::table('ocs')->where('id', $id)->update([
                    'CS' => $request->CS, 'CsDate' => $request->CsDate, 'SNo' => $request->SNo, 'Sname' => $request->Sname,
                    'Customer' => $request->Customer, 'customer_id' => $request->customer_id, 'Color' => $request->Color, 'ONum' => $request->ONum, 'CMT' => $request->CMT,
                    'order_type' => $request->order_type, 'material_ownership' => $request->material_ownership, 'unit_price' => $request->unit_price ?? 0,
                    'Qty' => $request->Qty, 'bom_header_id' => null,
                    'expected_ship_date' => $request->expected_ship_date, 'priority' => $request->priority ?? 'medium',
                    'order_notes' => $request->order_notes, 'updated_at' => now(),
                ]);
                DB::table('order_sizes')->where('cutsheet_id', $id)->delete();
                DB::table('order_sizes')->insert(collect($request->sizes)->map(fn ($size) => [
                    'cutsheet_id' => $id, 'size_name' => $size['size_name'], 'quantity' => $size['quantity'],
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
                [, $mappingStatus] = $this->createOrderBom((int) $id, $request->integer('bom_header_id') ?: null, $request->user()?->id);
                if ($currentOrder->bom_header_id) {
                    DB::table('bom_headers')->where('id', $currentOrder->bom_header_id)->where('bom_kind', 'order')->delete();
                }
                return $mappingStatus;
            });
            if ($mappingStatus === 'needs_mapping') {
                return redirect()->route('admin.ocs.bom-size-mapping', $id)
                    ->with('success', 'OCS updated. Complete BOM size mapping before confirmation.');
            }
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
        if ($this->isLocked($order)) return redirect()->route('admin.ocs.index')->with('error', 'Confirmed, in-production, or completed orders cannot be deleted.');
        try {
            $deleted = DB::transaction(function () use ($id, $order) {
                $deleted = DB::table('ocs')->where('id', $id)->delete();
                if ($deleted && $order->bom_header_id) {
                    DB::table('bom_headers')->where('id', $order->bom_header_id)->where('bom_kind', 'order')->delete();
                }
                return $deleted;
            });

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

    private function createOrderBom(int $orderId, ?int $templateId, ?int $userId): array
    {
        if (!$templateId) {
            DB::table('ocs')->where('id', $orderId)->update(['bom_header_id' => null]);
            return [null, 'not_applicable'];
        }
        $template = DB::table('bom_headers')->where('id', $templateId)->where('bom_kind', 'template')->firstOrFail();
        $order = DB::table('ocs')->find($orderId);
        $templateItems = DB::table('bom_items')->where('bom_header_id', $templateId)->orderBy('sort_order')->get();
        $needsMapping = $templateItems->contains(fn ($item) => ($item->size_rule ?? 'all') === 'map_on_order');
        $defaultCosts = DB::table('material_vendors')
            ->where('is_default_vendor', true)
            ->whereIn('material_id', $templateItems->pluck('material_id')->filter()->unique())
            ->select('material_id', DB::raw('MAX(unit_price) as unit_cost'))
            ->groupBy('material_id')->pluck('unit_cost', 'material_id');
        $totalFabric = 0;
        $totalTrim = 0;
        foreach ($templateItems as $item) {
            $unitCost = (float) ($defaultCosts[$item->material_id] ?? $item->unit_cost ?? 0);
            $itemCost = (float) $item->consumption_rate * $unitCost;
            if (in_array($item->material_type, ['fabric', 'lining', 'pocket'], true)) $totalFabric += $itemCost;
            else $totalTrim += $itemCost;
        }
        $status = $needsMapping ? 'needs_mapping' : 'ready';
        $orderBomId = DB::table('bom_headers')->insertGetId([
            'template_id' => $template->id, 'cutsheet_id' => $orderId, 'bom_kind' => 'order',
            'mapping_status' => $status, 'style_id' => $template->style_id ?? null,
            'customer_id' => $template->customer_id ?? null, 'style_no' => $template->style_no,
            'style_name' => $template->style_name, 'customer' => $template->customer,
            'version' => $template->version . '-' . $order->CS, 'status' => 'active',
            'total_fabric_cost' => $totalFabric, 'total_trim_cost' => $totalTrim,
            'total_labor_cost' => $template->total_labor_cost, 'total_cmt' => $template->total_cmt,
            'effective_date' => $template->effective_date, 'notes' => 'Order BOM cloned from template #' . $template->id,
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sizes = DB::table('order_sizes')->where('cutsheet_id', $orderId)->get();
        foreach ($templateItems as $source) {
            $copy = (array) $source;
            unset($copy['id']);
            $copy['bom_header_id'] = $orderBomId;
            $copy['unit_cost'] = (float) ($defaultCosts[$source->material_id] ?? $source->unit_cost ?? 0);
            $copy['total_cost'] = (float) $source->consumption_rate * $copy['unit_cost'];
            $copy['created_at'] = now();
            $copy['updated_at'] = now();
            $itemId = DB::table('bom_items')->insertGetId($copy);
            if (($source->size_rule ?? 'all') === 'all') {
                foreach ($sizes as $size) {
                    DB::table('bom_item_size_mappings')->insert([
                        'bom_item_id' => $itemId, 'order_size_id' => $size->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
        DB::table('ocs')->where('id', $orderId)->update(['bom_header_id' => $orderBomId, 'updated_at' => now()]);
        return [$orderBomId, $status];
    }

    public function bomSizeMapping(int $id)
    {
        $order = DB::table('ocs')->find($id);
        if (!$order || !$order->bom_header_id) abort(404);
        $bom = DB::table('bom_headers')->where('id', $order->bom_header_id)->where('bom_kind', 'order')->firstOrFail();
        $sizes = DB::table('order_sizes')->where('cutsheet_id', $id)->orderBy('id')->get();
        $items = DB::table('bom_items')->where('bom_header_id', $bom->id)->orderBy('sort_order')->get();
        $mapped = DB::table('bom_item_size_mappings')->whereIn('bom_item_id', $items->pluck('id'))
            ->get()->groupBy('bom_item_id');
        return view('admin.ocs.bom-size-mapping', compact('order', 'bom', 'sizes', 'items', 'mapped'));
    }

    public function saveBomSizeMapping(Request $request, int $id)
    {
        $order = DB::table('ocs')->find($id);
        if (!$order || !$order->bom_header_id || $this->isLocked($order)) abort(404);
        $bom = DB::table('bom_headers')->where('id', $order->bom_header_id)->where('bom_kind', 'order')->firstOrFail();
        $items = DB::table('bom_items')->where('bom_header_id', $bom->id)->get();
        $validSizeIds = DB::table('order_sizes')->where('cutsheet_id', $id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $submitted = $request->input('mappings', []);
        foreach ($items as $item) {
            $sizeIds = array_values(array_unique(array_map('intval', $submitted[$item->id] ?? [])));
            if (array_diff($sizeIds, $validSizeIds)) {
                return back()->with('error', 'Invalid size mapping submitted.');
            }
            if (($item->size_rule ?? 'all') === 'map_on_order' && !$sizeIds) {
                return back()->with('error', "Select at least one size for {$item->material_code}.");
            }
        }
        DB::transaction(function () use ($items, $submitted, $bom, $validSizeIds) {
            DB::table('bom_item_size_mappings')->whereIn('bom_item_id', $items->pluck('id'))->delete();
            foreach ($items as $item) {
                $sizeIds = ($item->size_rule ?? 'all') === 'all'
                    ? $validSizeIds : array_values(array_unique(array_map('intval', $submitted[$item->id] ?? [])));
                foreach ($sizeIds as $sizeId) DB::table('bom_item_size_mappings')->insert([
                    'bom_item_id' => $item->id, 'order_size_id' => $sizeId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('bom_headers')->where('id', $bom->id)->update(['mapping_status' => 'ready', 'updated_at' => now()]);
        });
        return redirect()->route('admin.ocs.index')->with('success', 'Order BOM size mapping is ready.');
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
            'status' => 'required|in:pending,confirmed,in_production,completed',
            'change_reason' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request, $id, $requisitions, $costings, $audit) {
                $order = DB::table('ocs')->where('id', $id)->lockForUpdate()->first();
                if (!$order) abort(404);
                $allowed = [
                    'pending' => ['confirmed'], 'confirmed' => ['in_production'],
                    'in_production' => ['completed'], 'completed' => [],
                ];
                if ($request->status !== $order->status && !in_array($request->status, $allowed[$order->status] ?? [], true)) {
                    throw new \RuntimeException("Cannot change status from '{$order->status}' to '{$request->status}'.");
                }
                if ($request->status === 'confirmed' && $request->status !== $order->status) {
                    $bom = $order->bom_header_id ? DB::table('bom_headers')->find($order->bom_header_id) : null;
                    if (!$bom || (($bom->bom_kind ?? 'template') === 'order' && ($bom->mapping_status ?? null) !== 'ready')) {
                        throw new \RuntimeException('Complete the Order BOM size mapping before confirmation.');
                    }
                    DB::table('ocs')->where('id', $id)->update(['requisition_job_status' => 'queued', 'requisition_job_error' => null, 'updated_at' => now()]);
                    CreateRequisitionForCutsheet::dispatch((int) $id)->afterCommit();
                }
                DB::table('ocs')->where('id', $id)->update(['status' => $request->status, 'updated_at' => now()]);
                if ($request->status !== $order->status) $audit->record('status_changed', 'order_cutsheet', (int) $id, $request->user()?->id, ['status' => $order->status], ['status' => $request->status], $request->change_reason ?: 'Workflow status transition');
                if ($request->status === 'completed' && $request->status !== $order->status) $costings->snapshot((int) $id);
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
