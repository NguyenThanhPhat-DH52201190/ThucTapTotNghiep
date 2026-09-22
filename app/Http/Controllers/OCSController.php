<?php

namespace App\Http\Controllers;

use App\Services\OrderCostSnapshotService;
use App\Jobs\CreateRequisitionForCutsheet;
use App\Services\RequisitionService;
use App\Services\OrderMaterialRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
            'Customer' => 'required', 'customer_id' => 'required|exists:customer_info,id', 'Color' => 'required', 'ONum' => 'required',
            'CMT' => 'nullable|numeric|min:0', 'Qty' => 'required|integer|min:1',
            'order_type' => 'required|in:cmt,fob', 'material_ownership' => 'required|in:factory,customer',
            'unit_price' => 'nullable|numeric|decimal:0,4|min:0',
            'bom_header_id' => 'nullable|exists:bom_headers,id',
            'expected_ship_date' => 'nullable|date', 'priority' => 'nullable|in:low,medium,high,urgent',
            'sizes' => 'required|array|min:1',
            'sizes.*.size_name' => 'required|string|max:50|distinct',
            'sizes.*.quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) return null;
        $path = $request->file('image')->store('ocs-images', 'local');
        if (!$path) throw new \RuntimeException('Unable to store the OCS image.');
        return $path;
    }

    private function deleteImage(?string $path): void
    {
        if (!$path) return;
        try {
            Storage::disk('local')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Unable to remove OCS image', ['path' => $path, 'message' => $e->getMessage()]);
        }
    }

    public function image(string $id)
    {
        $path = app(\App\Services\CustomerStyleService::class)->imagePath('ocs', (int) $id);
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

    private function validateCustomerSizes(Request $request): void
    {
        $allowed = DB::table('customer_sizes')->where('customer_id', $request->integer('customer_id'))
            ->pluck('size_name')->map(fn ($size) => mb_strtolower(trim($size)))->all();
        $submitted = collect($request->input('sizes', []))
            ->pluck('size_name')->map(fn ($size) => mb_strtolower(trim((string) $size)))->all();
        if (!$allowed || array_diff($submitted, $allowed)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sizes' => ['All OCS sizes must come from the selected customer size breakdown.'],
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
        if ((int) $bom->customer_id !== $request->integer('customer_id') || $bom->style_no !== $request->SNo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bom_header_id' => ['The BOM and OCS must belong to the same customer so product-size rules can be applied correctly.'],
            ]);
        }
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
            ->selectRaw(\App\Services\CustomerStyleService::imageSql('ocs', 'SNo').' as image_path')
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
        $customerSizes = DB::table('customer_sizes')->orderBy('sort_order')->get()->groupBy('customer_id');
        return view('admin.ocs.addocs', compact('boms', 'customers', 'customerSizes'));
    }

    public function store(Request $request, RequisitionService $requisitions)
    {
        app(\App\Services\CustomerStyleService::class)->apply($request, true);
        $request->validate($this->orderRules());
        $this->validateSizeTotal($request);
        $this->validateCustomerSizes($request);
        $this->validateBomForOrder($request);
        $imagePath = null;
        try {
            $imagePath = $this->storeImage($request);
            [$orderId, $mappingStatus] = DB::transaction(function () use ($request, $requisitions, $imagePath) {
                $orderId = DB::table('ocs')->insertGetId([
                    'CS' => $request->CS, 'CsDate' => $request->CsDate, 'SNo' => $request->SNo,
                    'Sname' => $request->Sname, 'Customer' => $request->Customer, 'customer_id' => $request->customer_id, 'Color' => $request->Color,
                    'ONum' => $request->ONum, 'CMT' => $request->CMT, 'Qty' => $request->Qty,
                    'order_type' => $request->order_type, 'material_ownership' => $request->material_ownership, 'unit_price' => $request->unit_price ?? 0,
                    'status' => 'pending', 'bom_header_id' => null,
                    'image_path' => $imagePath,
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
            if ($request->filled('bom_header_id')) {
                return redirect()->route('admin.norm.materials.show', $orderId)
                    ->with('success', 'OCS and material requirements created successfully.');
            }
            return redirect()->route('admin.ocs.index')->with('success', 'Order and size breakdown saved successfully.');
        } catch (\Throwable $e) {
            $this->deleteImage($imagePath);
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
        abort_if(!$order, 404);
        $boms = DB::table('bom_headers')->where('status', 'active')->where('bom_kind', 'template')->orderBy('style_no')->get();
        $assignedBom = $order->bom_header_id ? DB::table('bom_headers')->find($order->bom_header_id) : null;
        $order->selected_template_id = $assignedBom?->template_id ?: $order->bom_header_id;
        $customers = DB::table('customer_info')->orderBy('name')->get();
        $customerSizes = DB::table('customer_sizes')->orderBy('sort_order')->get()->groupBy('customer_id');
        $sizes = DB::table('order_sizes')->where('cutsheet_id', $id)->orderBy('id')->get();
        return view('admin.ocs.editocs', compact('order', 'boms', 'sizes', 'customers', 'customerSizes'));
    }

    public function update(Request $request, string $id, RequisitionService $requisitions)
    {
        $currentOrder = DB::table('ocs')->where('id', $id)->first();

        if (!$currentOrder) {
            return redirect()->route('admin.ocs.index')
                ->with('error', 'Record not found.');
        }

        app(\App\Services\CustomerStyleService::class)->apply($request, true);
        $request->validate($this->orderRules((int) $id));
        $this->validateSizeTotal($request);
        $this->validateCustomerSizes($request);
        $this->validateBomForOrder($request);
        try {
            $imagePath = $this->storeImage($request);
            $mappingStatus = DB::transaction(function () use ($request, $id, $requisitions, $currentOrder, $imagePath) {
                DB::table('ocs')->where('id', $id)->update([
                    'CS' => $request->CS, 'CsDate' => $request->CsDate, 'SNo' => $request->SNo, 'Sname' => $request->Sname,
                    'Customer' => $request->Customer, 'customer_id' => $request->customer_id, 'Color' => $request->Color, 'ONum' => $request->ONum, 'CMT' => $request->CMT,
                    'order_type' => $request->order_type, 'material_ownership' => $request->material_ownership, 'unit_price' => $request->unit_price ?? 0,
                    'Qty' => $request->Qty,
                    'image_path' => $imagePath ?? $currentOrder->image_path,
                    'expected_ship_date' => $request->expected_ship_date, 'priority' => $request->priority ?? 'medium',
                    'order_notes' => $request->order_notes, 'updated_at' => now(),
                ]);
                DB::table('order_sizes')->where('cutsheet_id', $id)->delete();
                DB::table('order_sizes')->insert(collect($request->sizes)->map(fn ($size) => [
                    'cutsheet_id' => $id, 'size_name' => $size['size_name'], 'quantity' => $size['quantity'],
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
                $assignedBom = $currentOrder->bom_header_id
                    ? DB::table('bom_headers')->find($currentOrder->bom_header_id) : null;
                // Keep the order's customized BOM and item references when only order details change.
                if ($assignedBom && (int) ($assignedBom->template_id ?: $assignedBom->id) === $request->integer('bom_header_id')) {
                    return $assignedBom->mapping_status;
                }
                [, $mappingStatus] = $this->createOrderBom((int) $id, $request->integer('bom_header_id') ?: null, $request->user()?->id);
                if ($currentOrder->bom_header_id) {
                    $oldItemIds = DB::table('bom_items')->where('bom_header_id', $currentOrder->bom_header_id)->pluck('id');
                    DB::table('bom_item_customer_sizes')->whereIn('bom_item_id', $oldItemIds)->delete();
                    DB::table('bom_headers')->where('id', $currentOrder->bom_header_id)->where('bom_kind', 'order')->delete();
                }
                return $mappingStatus;
            });
            if ($imagePath) $this->deleteImage($currentOrder->image_path);
            if ($request->filled('bom_header_id')) {
                return redirect()->route('admin.norm.materials.show', $id)
                    ->with('success', 'OCS and material requirements updated successfully.');
            }
            return redirect()->route('admin.ocs.index')->with('success', 'Order and size breakdown updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to update OCS order', ['message' => $e->getMessage(), 'id' => $id]);
            $this->deleteImage($imagePath ?? null);
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
                DB::table('order_material_requirements')->where('cutsheet_id', $id)->delete();
                $deleted = DB::table('ocs')->where('id', $id)->delete();
                if ($deleted && $order->bom_header_id) {
                    $itemIds = DB::table('bom_items')->where('bom_header_id', $order->bom_header_id)->pluck('id');
                    DB::table('bom_item_customer_sizes')->whereIn('bom_item_id', $itemIds)->delete();
                    DB::table('bom_headers')->where('id', $order->bom_header_id)->where('bom_kind', 'order')->delete();
                }
                return $deleted;
            });

            if (!$deleted) {
                return redirect()->route('admin.ocs.index')
                    ->with('error', 'Record not found.');
            }

            $this->deleteImage($order->image_path ?? null);
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
        $status = 'ready';
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
        foreach ($templateItems as $source) {
            $copy = (array) $source;
            unset($copy['id']);
            $copy['bom_header_id'] = $orderBomId;
            $copy['unit_cost'] = (float) ($defaultCosts[$source->material_id] ?? $source->unit_cost ?? 0);
            $copy['total_cost'] = (float) $source->consumption_rate * $copy['unit_cost'];
            $copy['created_at'] = now();
            $copy['updated_at'] = now();
            $newItemId = DB::table('bom_items')->insertGetId($copy);
            foreach (DB::table('bom_item_customer_sizes')->where('bom_item_id', $source->id)->pluck('customer_size_id') as $customerSizeId) {
                DB::table('bom_item_customer_sizes')->insert([
                    'bom_item_id' => $newItemId, 'customer_size_id' => $customerSizeId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        DB::table('ocs')->where('id', $orderId)->update(['bom_header_id' => $orderBomId, 'updated_at' => now()]);
        return [$orderBomId, $status];
    }

    public function materialRequirements(int $id, OrderMaterialRequirementService $materialRequirements)
    {
        $order = DB::table('ocs')->find($id);
        if (!$order) abort(404);
        if (!$order->bom_header_id) {
            return redirect()->route('admin.ocs.index')->with('error', 'Select a BOM before viewing material requirements.');
        }

        $bom = DB::table('bom_headers')->find($order->bom_header_id);
        if (!$bom) abort(404);

        $requirements = $materialRequirements->sync($id);

        return view('admin.ocs.material-requirements', compact('order', 'bom', 'requirements'));
    }

    public function exportMaterialRequirements(int $id, OrderMaterialRequirementService $materialRequirements)
    {
        $order = DB::table('ocs')->find($id);
        if (!$order || !$order->bom_header_id) abort(404);
        $rows = $materialRequirements->sync($id);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Material Requirements');
        $sheet->fromArray(['CS', 'Style', 'Material Code', 'Description', 'Type', 'Colour', 'Material Size', 'Unit', 'Product Qty', 'Yield', 'Waste %', 'Required', 'On Hand', 'Reserved', 'Available', 'Shortage', 'Status'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([
                $order->CS, $order->SNo, $row->material_code, $row->material_name, $row->material_type,
                $row->material_color, $row->material_size, $row->unit, (float) $row->product_qty,
                (float) $row->consumption_rate, (float) $row->waste_percent, (float) $row->required_qty,
                (float) $row->on_hand_qty, (float) $row->reserved_qty, (float) $row->available_qty,
                (float) $row->shortage_qty, ucfirst($row->stock_status),
            ], null, 'A' . ($index + 2));
        }
        $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
        foreach (range('A', 'Q') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $lastRow = max(2, $rows->count() + 1);
        $sheet->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("J2:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
        $sheet->getStyle("K2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("L2:P{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'material-requirements-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $order->CS) . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
                    if (!$bom) throw new \RuntimeException('Assign a BOM before confirmation.');
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
