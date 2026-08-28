<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BOMController extends Controller
{
    private function resolveStyleId(string $styleNo, ?string $styleName): int
    {
        $style = DB::table('styles')->where('style_no', trim($styleNo))->first();
        if ($style) {
            if ($styleName && $style->style_name !== $styleName) {
                DB::table('styles')->where('id', $style->id)->update(['style_name' => $styleName, 'updated_at' => now()]);
            }
            return (int) $style->id;
        }
        return DB::table('styles')->insertGetId([
            'style_no' => trim($styleNo), 'style_name' => $styleName ?: trim($styleNo),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function isAdmin(Request $request): bool
    {
        return $request->user()?->role === 'admin';
    }

    private function getBomList(Request $request)
    {
        return DB::table('bom_headers')
            ->when($request->filled('style_no'), function ($q) use ($request) {
                $q->where('style_no', 'like', '%' . $request->style_no . '%');
            })
            ->when($request->filled('customer'), function ($q) use ($request) {
                $q->where('customer', 'like', '%' . $request->customer . '%');
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function index(Request $request)
    {
        $boms = $this->getBomList($request);
        $styles = DB::table('ocs')->select('SNo', 'Sname', 'Customer')->distinct()->orderBy('SNo')->get();
        return view('admin.bom.index', compact('boms', 'styles'));
    }

    public function create()
    {
        $styles = DB::table('ocs')->select('SNo', 'Sname', 'Customer')->distinct()->orderBy('SNo')->get();
        return view('admin.bom.create', compact('styles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'style_no' => 'required',
            'style_name' => 'nullable',
            'customer' => 'nullable',
            'version' => 'nullable',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable',
            'items' => 'required|array|min:1',
            'items.*.material_code' => 'required',
            'items.*.material_name' => 'required',
            'items.*.material_type' => 'required',
            'items.*.colour' => 'nullable',
            'items.*.size' => 'nullable',
            'items.*.width' => 'nullable|numeric',
            'items.*.unit' => 'nullable',
            'items.*.consumption_rate' => 'required|numeric|gt:0',
            'items.*.waste_percent' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.remark' => 'nullable',
        ]);

        try {
            DB::beginTransaction();

            // Calculate totals
            $totalFabric = 0;
            $totalTrim = 0;

            foreach ($request->items as $item) {
                $totalCost = ($item['consumption_rate'] ?? 0) * ($item['unit_cost'] ?? 0);
                $type = $item['material_type'] ?? 'other';
                if (in_array($type, ['fabric', 'lining', 'pocket'])) {
                    $totalFabric += $totalCost;
                } else {
                    $totalTrim += $totalCost;
                }
            }

            $styleId = $this->resolveStyleId($request->style_no, $request->style_name);
            $headerId = DB::table('bom_headers')->insertGetId([
                'style_id' => $styleId,
                'style_no' => $request->style_no,
                'style_name' => $request->style_name ?? '',
                'customer' => $request->customer ?? '',
                'version' => $request->version ?? 'V1',
                'status' => 'draft',
                'total_fabric_cost' => $totalFabric,
                'total_trim_cost' => $totalTrim,
                'total_cmt' => 0,
                'effective_date' => $request->effective_date,
                'notes' => $request->notes,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $i => $item) {
                $totalCost = ($item['consumption_rate'] ?? 0) * ($item['unit_cost'] ?? 0);
                DB::table('bom_items')->insert([
                    'bom_header_id' => $headerId,
                    'material_code' => $item['material_code'],
                    'material_name' => $item['material_name'],
                    'material_type' => $item['material_type'],
                    'colour' => $item['colour'] ?? null,
                    'size' => $item['size'] ?? null,
                    'width' => $item['width'] ?? null,
                    'unit' => $item['unit'] ?? 'M',
                    'consumption_rate' => $item['consumption_rate'] ?? 0,
                    'waste_percent' => $item['waste_percent'] ?? 0,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'total_cost' => $totalCost,
                    'source' => $item['source'] ?? 'local',
                    'remark' => $item['remark'] ?? null,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            app(\App\Services\AuditTrailService::class)->record('bom_created', 'bom_header', (int) $headerId, $request->user()?->id, [], ['style_no' => $request->style_no, 'version' => $request->version ?? 'V1', 'item_count' => count($request->items)]);

            return redirect()->route('admin.bom.index')
                ->with('success', 'BOM created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BOM create failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to create BOM: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show($id)
    {
        $bom = DB::table('bom_headers')->where('id', $id)->first();
        if (!$bom) abort(404);
        $items = DB::table('bom_items')->where('bom_header_id', $id)->orderBy('sort_order')->get();
        $styles = DB::table('ocs')->select('SNo', 'Sname', 'Customer')->distinct()->orderBy('SNo')->get();
        $techPack = DB::table('tech_packs')->where('bom_header_id', $id)->first();
        $colorways = DB::table('bom_colorways')->join('bom_items', 'bom_colorways.bom_item_id', '=', 'bom_items.id')
            ->where('bom_items.bom_header_id', $id)->select('bom_colorways.*', 'bom_items.material_code', 'bom_items.material_name')->get();
        return view('admin.bom.show', compact('bom', 'items', 'styles', 'techPack', 'colorways'));
    }

    public function clone(Request $request, $id)
    {
        $data = $request->validate([
            'style_no' => 'required|string|max:191', 'style_name' => 'nullable|string|max:191',
            'customer' => 'nullable|string|max:191', 'version' => 'nullable|string|max:50',
        ]);
        try {
            $cloneId = DB::transaction(function () use ($id, $data, $request) {
                $source = DB::table('bom_headers')->where('id', $id)->lockForUpdate()->first();
                if (!$source) abort(404);
                $styleId = $this->resolveStyleId($data['style_no'], $data['style_name'] ?? $source->style_name);
                $cloneId = DB::table('bom_headers')->insertGetId([
                    'style_id' => $styleId, 'style_no' => $data['style_no'], 'style_name' => $data['style_name'] ?? $source->style_name,
                    'customer' => $data['customer'] ?? $source->customer, 'version' => $data['version'] ?? 'V1',
                    'status' => 'draft', 'total_fabric_cost' => $source->total_fabric_cost,
                    'total_trim_cost' => $source->total_trim_cost, 'total_labor_cost' => $source->total_labor_cost,
                    'total_cmt' => $source->total_cmt, 'effective_date' => $source->effective_date,
                    'notes' => $source->notes, 'created_by' => $request->user()->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (DB::table('bom_items')->where('bom_header_id', $id)->orderBy('sort_order')->get() as $item) {
                    $copy = (array) $item; unset($copy['id']);
                    $copy['bom_header_id'] = $cloneId; $copy['created_at'] = now(); $copy['updated_at'] = now();
                    $newItemId = DB::table('bom_items')->insertGetId($copy);
                    foreach (DB::table('bom_colorways')->where('bom_item_id', $item->id)->get() as $colorway) {
                        DB::table('bom_colorways')->insert([
                            'bom_item_id' => $newItemId, 'garment_color' => $colorway->garment_color,
                            'material_color' => $colorway->material_color, 'notes' => $colorway->notes,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                return $cloneId;
            });
            return redirect()->route('admin.bom.edit', $cloneId)->with('success', 'BOM cloned. Review and activate the new draft.');
        } catch (\Throwable $e) {
            Log::warning('BOM clone failed', ['bom_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function saveTechPack(Request $request, $id)
    {
        $bom = DB::table('bom_headers')->find($id); if (!$bom) abort(404);
        $data = $request->validate([
            'size_spec' => 'nullable|json', 'color_way' => 'nullable|json',
            'sewing_instructions' => 'nullable|string', 'cutting_instructions' => 'nullable|string',
            'finishing_instructions' => 'nullable|string', 'packing_instructions' => 'nullable|string',
            'sample_image' => 'nullable|image|max:5120', 'status' => 'nullable|in:draft,approved,archived', 'change_reason' => 'required|string|max:255',
        ]);
        $before = DB::table('tech_packs')->where('bom_header_id', $id)->first();
        $reason = $data['change_reason']; unset($data['change_reason']);
        $image = $request->hasFile('sample_image') ? $request->file('sample_image')->store('tech-packs', 'public') : null;
        DB::table('tech_packs')->updateOrInsert(['bom_header_id' => $id], [
            'style_no' => $bom->style_no, 'style_name' => $bom->style_name, 'customer' => $bom->customer,
            'size_spec' => $data['size_spec'] ?: null, 'color_way' => $data['color_way'] ?: null,
            'sewing_instructions' => $data['sewing_instructions'] ?? null,
            'cutting_instructions' => $data['cutting_instructions'] ?? null,
            'finishing_instructions' => $data['finishing_instructions'] ?? null,
            'packing_instructions' => $data['packing_instructions'] ?? null, 'status' => $data['status'] ?? 'draft',
            'sample_image' => $image ?? ($tech = DB::table('tech_packs')->where('bom_header_id', $id)->value('sample_image')),
            'created_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now(),
        ]);
        app(\App\Services\AuditTrailService::class)->record('tech_pack_updated', 'bom_header', (int) $id, $request->user()?->id,
            $before ? ['status' => $before->status, 'updated_at' => $before->updated_at] : [], ['status' => $data['status'] ?? 'draft'], $reason);
        return back()->with('success', 'Tech Pack saved.');
    }

    public function saveColorways(Request $request, $id)
    {
        $bom = DB::table('bom_headers')->find($id); if (!$bom) abort(404);
        $data = $request->validate([
            'colorways' => 'required|array|min:1',
            'colorways.*.bom_item_id' => 'required|integer',
            'colorways.*.garment_color' => 'required|string|max:191',
            'colorways.*.material_color' => 'required|string|max:191',
            'colorways.*.notes' => 'nullable|string', 'change_reason' => 'required|string|max:255',
        ]);
        $reason = $data['change_reason']; unset($data['change_reason']);
        DB::transaction(function () use ($data, $id) {
            foreach ($data['colorways'] as $row) {
                $validItem = DB::table('bom_items')->where('id', $row['bom_item_id'])->where('bom_header_id', $id)->exists();
                if (!$validItem) throw new \RuntimeException('A colorway item does not belong to this BOM.');
                DB::table('bom_colorways')->updateOrInsert([
                    'bom_item_id' => $row['bom_item_id'], 'garment_color' => trim($row['garment_color']),
                ], [
                    'material_color' => trim($row['material_color']), 'notes' => $row['notes'] ?? null, 'updated_at' => now(), 'created_at' => now(),
                ]);
            }
        });
        app(\App\Services\AuditTrailService::class)->record('bom_colorway_updated', 'bom_header', (int) $id, $request->user()?->id, [], ['colorways' => $data['colorways']], $reason);
        return back()->with('success', 'BOM colorway mapping saved. Requisitions will now use the mapped material color.');
    }

    public function edit($id)
    {
        $bom = DB::table('bom_headers')->where('id', $id)->first();
        if (!$bom) abort(404);
        $items = DB::table('bom_items')->where('bom_header_id', $id)->orderBy('sort_order')->get();
        $styles = DB::table('ocs')->select('SNo', 'Sname', 'Customer')->distinct()->orderBy('SNo')->get();
        return view('admin.bom.edit', compact('bom', 'items', 'styles'));
    }

    public function update(Request $request, $id)
    {
        $bom = DB::table('bom_headers')->where('id', $id)->first();
        if (!$bom) abort(404);
        $beforeItemCount = DB::table('bom_items')->where('bom_header_id', $id)->count();

        $validated = $request->validate([
            'style_no' => 'required',
            'style_name' => 'nullable',
            'customer' => 'nullable',
            'version' => 'nullable',
            'status' => 'nullable',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable', 'change_reason' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.material_code' => 'required',
            'items.*.material_name' => 'required',
            'items.*.material_type' => 'required',
            'items.*.colour' => 'nullable',
            'items.*.size' => 'nullable',
            'items.*.width' => 'nullable|numeric',
            'items.*.unit' => 'nullable',
            'items.*.consumption_rate' => 'required|numeric|gt:0',
            'items.*.waste_percent' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.remark' => 'nullable',
        ]);

        try {
            DB::beginTransaction();

            $totalFabric = 0;
            $totalTrim = 0;

            foreach ($request->items as $item) {
                $totalCost = ($item['consumption_rate'] ?? 0) * ($item['unit_cost'] ?? 0);
                $type = $item['material_type'] ?? 'other';
                if (in_array($type, ['fabric', 'lining', 'pocket'])) {
                    $totalFabric += $totalCost;
                } else {
                    $totalTrim += $totalCost;
                }
            }

            $styleId = $this->resolveStyleId($request->style_no, $request->style_name);
            DB::table('bom_headers')->where('id', $id)->update([
                'style_id' => $styleId,
                'style_no' => $request->style_no,
                'style_name' => $request->style_name ?? '',
                'customer' => $request->customer ?? '',
                'version' => $request->version ?? 'V1',
                'status' => $request->status ?? 'draft',
                'total_fabric_cost' => $totalFabric,
                'total_trim_cost' => $totalTrim,
                'effective_date' => $request->effective_date,
                'notes' => $request->notes,
                'updated_at' => now(),
            ]);

            // Delete old items, re-insert
            DB::table('bom_items')->where('bom_header_id', $id)->delete();

            foreach ($request->items as $i => $item) {
                $totalCost = ($item['consumption_rate'] ?? 0) * ($item['unit_cost'] ?? 0);
                DB::table('bom_items')->insert([
                    'bom_header_id' => $id,
                    'material_code' => $item['material_code'],
                    'material_name' => $item['material_name'],
                    'material_type' => $item['material_type'],
                    'colour' => $item['colour'] ?? null,
                    'size' => $item['size'] ?? null,
                    'width' => $item['width'] ?? null,
                    'unit' => $item['unit'] ?? 'M',
                    'consumption_rate' => $item['consumption_rate'] ?? 0,
                    'waste_percent' => $item['waste_percent'] ?? 0,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'total_cost' => $totalCost,
                    'source' => $item['source'] ?? 'local',
                    'remark' => $item['remark'] ?? null,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            app(\App\Services\AuditTrailService::class)->record('bom_updated', 'bom_header', (int) $id, $request->user()?->id,
                ['style_no' => $bom->style_no, 'version' => $bom->version, 'status' => $bom->status, 'item_count' => $beforeItemCount],
                ['style_no' => $request->style_no, 'version' => $request->version ?? 'V1', 'status' => $request->status ?? 'draft', 'item_count' => count($request->items)], $request->change_reason);

            return redirect()->route('admin.bom.index')
                ->with('success', 'BOM updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BOM update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update BOM: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        DB::table('bom_headers')->where('id', $id)->delete();
        return redirect()->route('admin.bom.index')
            ->with('success', 'BOM deleted successfully!');
    }

    public function export($id)
    {
        $bom = DB::table('bom_headers')->where('id', $id)->first();
        if (!$bom) abort(404);
        $items = DB::table('bom_items')->where('bom_header_id', $id)->orderBy('sort_order')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BOM');

        // Header info
        $sheet->setCellValue('A1', 'BOM: ' . $bom->style_no . ' - ' . $bom->style_name);
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A3', 'Style No:');
        $sheet->setCellValue('B3', $bom->style_no);
        $sheet->setCellValue('A4', 'Customer:');
        $sheet->setCellValue('B4', $bom->customer);
        $sheet->setCellValue('A5', 'Version:');
        $sheet->setCellValue('B5', $bom->version);
        $sheet->setCellValue('A6', 'Status:');
        $sheet->setCellValue('B6', $bom->status);

        // Headers
        $headers = ['No.', 'Code', 'Description', 'Colour', 'Size', 'Width', 'Unit', 'Yield', 'Remark', 'Type'];
        $row = 8;
        foreach ($headers as $index => $header) {
            $col = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
        }

        $row = 9;
        foreach ($items as $i => $item) {
            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $item->material_code);
            $sheet->setCellValue('C' . $row, $item->material_name);
            $sheet->setCellValue('D' . $row, $item->colour ?? '');
            $sheet->setCellValue('E' . $row, $item->size ?? '');
            $sheet->setCellValue('F' . $row, $item->width ?? '');
            $sheet->setCellValue('G' . $row, $item->unit ?? '');
            $sheet->setCellValue('H' . $row, $item->consumption_rate);
            $sheet->setCellValue('I' . $row, $item->remark ?? '');
            $sheet->setCellValue('J' . $row, $item->material_type);
            $row++;
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'BOM-' . $bom->style_no . '-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $path = $request->file('file')->getRealPath();
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Find header row (look for Code, Description, Yield columns)
        $headerRow = null;
        $colMap = [];
        foreach ($rows as $idx => $row) {
            $normalized = array_map('strtolower', array_map('trim', $row));
            if (in_array('code', $normalized) || in_array('material_code', $normalized)) {
                $headerRow = $idx;
                foreach ($normalized as $ci => $val) {
                    if (in_array($val, ['code', 'material_code'])) $colMap['material_code'] = $ci;
                    elseif (in_array($val, ['description', 'material_name', 'desc'])) $colMap['material_name'] = $ci;
                    elseif (in_array($val, ['colour', 'color'])) $colMap['colour'] = $ci;
                    elseif (in_array($val, ['size'])) $colMap['size'] = $ci;
                    elseif (in_array($val, ['width', 'khổ'])) $colMap['width'] = $ci;
                    elseif (in_array($val, ['unit'])) $colMap['unit'] = $ci;
                    elseif (in_array($val, ['yield', 'consumption_rate', 'consumption'])) $colMap['consumption_rate'] = $ci;
                    elseif (in_array($val, ['remark', 'note', 'notes'])) $colMap['remark'] = $ci;
                    elseif (in_array($val, ['stt', 'no.', 'no', '#'])) $colMap['stt'] = $ci;
                }
                break;
            }
        }

        if ($headerRow === null) {
            return back()->with('error', 'Could not detect BOM columns in the file. Expected: Code, Description, Yield');
        }

        $parsedItems = [];
        for ($i = $headerRow + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            // Skip empty rows
            if (empty(array_filter($row))) continue;

            $item = [
                'material_code' => $row[$colMap['material_code']] ?? '',
                'material_name' => $row[$colMap['material_name']] ?? ($row[$colMap['material_code']] ?? ''),
                'colour' => $row[$colMap['colour']] ?? '',
                'size' => $row[$colMap['size']] ?? '',
                'width' => $row[$colMap['width']] ?? '',
                'unit' => $row[$colMap['unit']] ?? 'M',
                'consumption_rate' => is_numeric($row[$colMap['consumption_rate']] ?? '') ? (float)$row[$colMap['consumption_rate']] : 0,
                'remark' => $row[$colMap['remark']] ?? '',
                'material_type' => 'other',
            ];

            if (!empty($item['material_code'])) {
                // Auto-detect material type from code or description
                $code = strtolower($item['material_code']);
                $desc = strtolower($item['material_name']);
                if (strpos($code, 'v-') !== false || strpos($code, 'fabric') !== false || strpos($desc, 'vải') !== false) {
                    $item['material_type'] = 'fabric';
                } elseif (strpos($code, 'lót') !== false || strpos($desc, 'lót') !== false || strpos($code, 'lining') !== false) {
                    $item['material_type'] = 'lining';
                } elseif (strpos($code, 'zip') !== false || strpos($code, 'yk') !== false || strpos($desc, 'dây kéo') !== false || strpos($desc, 'khóa') !== false) {
                    $item['material_type'] = 'zipper';
                } elseif (strpos($code, 'chỉ') !== false || strpos($desc, 'chỉ') !== false || strpos($code, 'thread') !== false) {
                    $item['material_type'] = 'thread';
                } elseif (strpos($desc, 'nhãn') !== false || strpos($code, 'label') !== false) {
                    $item['material_type'] = 'label';
                } elseif (strpos($desc, 'thun') !== false || strpos($code, 'elastic') !== false) {
                    $item['material_type'] = 'elastic';
                } else {
                    $item['material_type'] = 'trim';
                }

                $parsedItems[] = $item;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return view('admin.bom.import-preview', [
            'parsedItems' => $parsedItems,
            'styles' => DB::table('ocs')->select('SNo', 'Sname', 'Customer')->distinct()->orderBy('SNo')->get(),
        ]);
    }

    public function importStore(Request $request)
    {
        // Store BOM from import preview
        return $this->store($request);
    }
}
