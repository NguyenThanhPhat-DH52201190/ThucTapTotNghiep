<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BOMController extends Controller
{
    private function customers()
    {
        return DB::table('customer_info')->select('id', 'name', 'brand')->orderBy('name')->get();
    }

    private function customerSizes()
    {
        return DB::table('customer_sizes')->select('id', 'customer_id', 'size_name')
            ->orderBy('customer_id')->orderBy('sort_order')->orderBy('size_name')->get()->groupBy('customer_id');
    }

    private function materialCategories()
    {
        return DB::table('material_categories')->select('id', 'name', 'slug')->orderBy('name')->get();
    }

    private function validatedItemMaterials(Request $request): array
    {
        $result = [];
        foreach ((array) $request->input('items', []) as $index => $item) {
            $material = DB::table('materials')->where('internal_code', trim((string) ($item['material_code'] ?? '')))->first();
            if (!$material || (int) $material->category_id !== (int) ($item['category_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    "items.$index.material_code" => 'The selected material must belong to the selected Type.',
                ]);
            }
            $result[$index] = $material;
        }
        return $result;
    }

    private function validatedItemSizeMappings(Request $request): array
    {
        $customerId = $request->integer('customer_id');
        $allowed = $customerId
            ? DB::table('customer_sizes')->where('customer_id', $customerId)->pluck('id')->map(fn ($id) => (int) $id)
            : collect();
        $result = [];
        foreach ((array) $request->input('items', []) as $index => $item) {
            $raw = collect((array) ($item['customer_size_ids'] ?? ['all']))->map(fn ($value) => (string) $value);
            if ($raw->contains('all') || $raw->isEmpty()) {
                $result[$index] = [];
                continue;
            }
            $ids = $raw->filter(fn ($value) => ctype_digit($value))->map(fn ($value) => (int) $value)->unique()->values();
            if (!$customerId || $ids->count() !== $raw->count() || $ids->diff($allowed)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    "items.$index.customer_size_ids" => 'Product sizes must belong to the customer selected for this BOM.',
                ]);
            }
            $result[$index] = $ids->all();
        }
        return $result;
    }

    private function saveItemSizeMappings(int $bomItemId, array $customerSizeIds): void
    {
        foreach ($customerSizeIds as $customerSizeId) {
            DB::table('bom_item_customer_sizes')->insert([
                'bom_item_id' => $bomItemId, 'customer_size_id' => $customerSizeId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function resolveCustomer(?int $customerId, ?string $fallbackName = null): array
    {
        $customer = $customerId ? DB::table('customer_info')->find($customerId) : null;

        return [
            'customer_id' => $customer?->id,
            'customer' => $customer?->name ?? trim((string) $fallbackName),
        ];
    }

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

    private function defaultMaterialCosts(array $items, array $fallback = []): array
    {
        $codes = collect($items)->pluck('material_code')->filter()->unique()->values();
        if ($codes->isEmpty()) return $fallback;

        $mapped = DB::table('materials')
            ->join('material_vendors', function ($join) {
                $join->on('material_vendors.material_id', '=', 'materials.id')
                    ->where('material_vendors.is_default_vendor', true);
            })
            ->whereIn('materials.internal_code', $codes)
            ->select('materials.internal_code', DB::raw('MAX(material_vendors.unit_price) as unit_cost'))
            ->groupBy('materials.internal_code')
            ->pluck('unit_cost', 'internal_code')
            ->map(fn ($cost) => (float) $cost)
            ->all();

        return array_replace($fallback, $mapped);
    }

    private function getBomList(Request $request)
    {
        return DB::table('bom_headers')
            ->where('bom_kind', 'template')
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
        $customers = $this->customers();
        $customerSizes = $this->customerSizes();
        $materialCategories = $this->materialCategories();
        return view('admin.bom.create', compact('styles', 'customers', 'customerSizes', 'materialCategories'));
    }

    public function materialSuggestions(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|min:1|max:100', 'category_id' => 'required|integer|exists:material_categories,id']);
        $term = trim($data['q']);

        return response()->json(
            DB::table('materials')
                ->select('id', 'category_id', 'internal_code', 'material_name', 'material_type', 'color', 'size', 'unit')
                ->where('category_id', $data['category_id'])
                ->where('internal_code', 'like', '%' . $term . '%')
                ->orderByRaw('CASE WHEN internal_code LIKE ? THEN 0 ELSE 1 END', [$term . '%'])
                ->orderBy('internal_code')
                ->limit(20)
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'style_no' => 'required',
            'style_name' => 'nullable',
            'customer_id' => 'nullable|exists:customer_info,id',
            'customer' => 'nullable',
            'version' => 'nullable',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable',
            'items' => 'required|array|min:1',
            'items.*.material_code' => 'required|string|max:191|exists:materials,internal_code',
            'items.*.material_name' => 'nullable|string|max:191',
            'items.*.material_type' => 'nullable',
            'items.*.category_id' => 'required|integer|exists:material_categories,id',
            'items.*.colour' => 'nullable',
            'items.*.size' => 'nullable',
            'items.*.width' => 'nullable|numeric',
            'items.*.unit' => 'nullable',
            'items.*.consumption_rate' => 'required|numeric|gt:0',
            'items.*.waste_percent' => 'nullable|numeric|min:0',
            'items.*.remark' => 'nullable',
            'items.*.customer_size_ids' => 'nullable|array',
        ]);
        $itemSizeMappings = $this->validatedItemSizeMappings($request);
        $itemMaterials = $this->validatedItemMaterials($request);

        try {
            DB::beginTransaction();

            // Calculate totals
            $totalFabric = 0;
            $totalTrim = 0;
            $unitCosts = $this->defaultMaterialCosts($request->items);

            foreach ($request->items as $i => $item) {
                $unitCost = $unitCosts[$item['material_code']] ?? 0;
                $totalCost = ($item['consumption_rate'] ?? 0) * $unitCost;
                $type = $itemMaterials[$i]->material_type ?? 'other';
                if (in_array($type, ['fabric', 'lining', 'pocket'])) {
                    $totalFabric += $totalCost;
                } else {
                    $totalTrim += $totalCost;
                }
            }

            $styleId = $this->resolveStyleId($request->style_no, $request->style_name);
            $customer = $this->resolveCustomer($request->integer('customer_id') ?: null, $request->customer);
            $headerId = DB::table('bom_headers')->insertGetId([
                'style_id' => $styleId,
                'customer_id' => $customer['customer_id'],
                'style_no' => $request->style_no,
                'style_name' => $request->style_name ?? '',
                'customer' => $customer['customer'],
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
                $material = $itemMaterials[$i];
                $unitCost = $unitCosts[$item['material_code']] ?? 0;
                $totalCost = ($item['consumption_rate'] ?? 0) * $unitCost;
                $bomItemId = DB::table('bom_items')->insertGetId([
                    'bom_header_id' => $headerId,
                    'material_id' => $material->id,
                    'material_code' => $material->internal_code,
                    'material_name' => $material->material_name,
                    'material_type' => $material->material_type,
                    'colour' => $material->color,
                    'size' => $material->size,
                    'size_rule' => 'all',
                    'width' => $item['width'] ?? null,
                    'unit' => $material->unit,
                    'consumption_rate' => $item['consumption_rate'] ?? 0,
                    'waste_percent' => $item['waste_percent'] ?? 0,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'source' => $item['source'] ?? 'local',
                    'remark' => $item['remark'] ?? null,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->saveItemSizeMappings($bomItemId, $itemSizeMappings[$i] ?? []);
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
        $customers = $this->customers();
        $itemSizeNames = DB::table('bom_item_customer_sizes')
            ->join('customer_sizes', 'customer_sizes.id', '=', 'bom_item_customer_sizes.customer_size_id')
            ->whereIn('bom_item_customer_sizes.bom_item_id', $items->pluck('id'))
            ->select('bom_item_customer_sizes.bom_item_id', 'customer_sizes.size_name')->get()->groupBy('bom_item_id');
        $techPack = DB::table('tech_packs')->where('bom_header_id', $id)->first();
        $colorways = DB::table('bom_colorways')->join('bom_items', 'bom_colorways.bom_item_id', '=', 'bom_items.id')
            ->where('bom_items.bom_header_id', $id)->select('bom_colorways.*', 'bom_items.material_code', 'bom_items.material_name')->get();
        return view('admin.bom.show', compact('bom', 'items', 'styles', 'customers', 'techPack', 'colorways', 'itemSizeNames'));
    }

    public function clone(Request $request, $id)
    {
        $data = $request->validate([
            'style_no' => 'required|string|max:191', 'style_name' => 'nullable|string|max:191',
            'customer_id' => 'nullable|exists:customer_info,id', 'customer' => 'nullable|string|max:191', 'version' => 'nullable|string|max:50',
        ]);
        try {
            $cloneId = DB::transaction(function () use ($id, $data, $request) {
                $source = DB::table('bom_headers')->where('id', $id)->lockForUpdate()->first();
                if (!$source) abort(404);
                $styleId = $this->resolveStyleId($data['style_no'], $data['style_name'] ?? $source->style_name);
                $customer = $this->resolveCustomer($data['customer_id'] ?? $source->customer_id, $data['customer'] ?? $source->customer);
                $cloneId = DB::table('bom_headers')->insertGetId([
                    'style_id' => $styleId, 'style_no' => $data['style_no'], 'style_name' => $data['style_name'] ?? $source->style_name,
                    'customer_id' => $customer['customer_id'], 'customer' => $customer['customer'], 'version' => $data['version'] ?? 'V1',
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
                    if ((int) $customer['customer_id'] === (int) $source->customer_id) {
                        foreach (DB::table('bom_item_customer_sizes')->where('bom_item_id', $item->id)->pluck('customer_size_id') as $customerSizeId) {
                            $this->saveItemSizeMappings($newItemId, [(int) $customerSizeId]);
                        }
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
        $customers = $this->customers();
        $customerSizes = $this->customerSizes();
        $materialCategories = $this->materialCategories();
        $materialCategoryIds = DB::table('materials')->whereIn('id', $items->pluck('material_id')->filter())
            ->pluck('category_id', 'id');
        $itemSizeMappings = DB::table('bom_item_customer_sizes')->whereIn('bom_item_id', $items->pluck('id'))
            ->get()->groupBy('bom_item_id')->map(fn ($rows) => $rows->pluck('customer_size_id')->map(fn ($id) => (int) $id)->values());
        return view('admin.bom.edit', compact('bom', 'items', 'styles', 'customers', 'customerSizes', 'itemSizeMappings', 'materialCategories', 'materialCategoryIds'));
    }

    public function update(Request $request, $id)
    {
        $bom = DB::table('bom_headers')->where('id', $id)->first();
        if (!$bom) abort(404);
        $beforeItemCount = DB::table('bom_items')->where('bom_header_id', $id)->count();

        $validated = $request->validate([
            'style_no' => 'required',
            'style_name' => 'nullable',
            'customer_id' => 'nullable|exists:customer_info,id',
            'customer' => 'nullable',
            'version' => 'nullable',
            'status' => 'nullable',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable', 'change_reason' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.material_code' => 'required|string|max:191|exists:materials,internal_code',
            'items.*.material_name' => 'nullable|string|max:191',
            'items.*.material_type' => 'nullable',
            'items.*.category_id' => 'required|integer|exists:material_categories,id',
            'items.*.colour' => 'nullable',
            'items.*.size' => 'nullable',
            'items.*.width' => 'nullable|numeric',
            'items.*.unit' => 'nullable',
            'items.*.consumption_rate' => 'required|numeric|gt:0',
            'items.*.waste_percent' => 'nullable|numeric|min:0',
            'items.*.remark' => 'nullable',
            'items.*.customer_size_ids' => 'nullable|array',
        ]);
        $itemSizeSelections = $this->validatedItemSizeMappings($request);
        $itemMaterials = $this->validatedItemMaterials($request);

        try {
            DB::beginTransaction();

            $totalFabric = 0;
            $totalTrim = 0;
            $existingCosts = DB::table('bom_items')->where('bom_header_id', $id)
                ->pluck('unit_cost', 'material_code')->map(fn ($cost) => (float) $cost)->all();
            $unitCosts = $this->defaultMaterialCosts($request->items, $existingCosts);

            foreach ($request->items as $i => $item) {
                $unitCost = $unitCosts[$item['material_code']] ?? 0;
                $totalCost = ($item['consumption_rate'] ?? 0) * $unitCost;
                $type = $itemMaterials[$i]->material_type ?? 'other';
                if (in_array($type, ['fabric', 'lining', 'pocket'])) {
                    $totalFabric += $totalCost;
                } else {
                    $totalTrim += $totalCost;
                }
            }

            $styleId = $this->resolveStyleId($request->style_no, $request->style_name);
            $customer = $this->resolveCustomer($request->integer('customer_id') ?: null, $request->customer);
            DB::table('bom_headers')->where('id', $id)->update([
                'style_id' => $styleId,
                'customer_id' => $customer['customer_id'],
                'style_no' => $request->style_no,
                'style_name' => $request->style_name ?? '',
                'customer' => $customer['customer'],
                'version' => $request->version ?? 'V1',
                'status' => $request->status ?? 'draft',
                'total_fabric_cost' => $totalFabric,
                'total_trim_cost' => $totalTrim,
                'effective_date' => $request->effective_date,
                'notes' => $request->notes,
                'updated_at' => now(),
            ]);

            // Delete old items, re-insert
            $oldItemIds = DB::table('bom_items')->where('bom_header_id', $id)->pluck('id');
            DB::table('bom_item_customer_sizes')->whereIn('bom_item_id', $oldItemIds)->delete();
            DB::table('bom_items')->where('bom_header_id', $id)->delete();

            foreach ($request->items as $i => $item) {
                $material = $itemMaterials[$i];
                $unitCost = $unitCosts[$item['material_code']] ?? 0;
                $totalCost = ($item['consumption_rate'] ?? 0) * $unitCost;
                $bomItemId = DB::table('bom_items')->insertGetId([
                    'bom_header_id' => $id,
                    'material_id' => $material->id,
                    'material_code' => $material->internal_code,
                    'material_name' => $material->material_name,
                    'material_type' => $material->material_type,
                    'colour' => $material->color,
                    'size' => $material->size,
                    'size_rule' => 'all',
                    'width' => $item['width'] ?? null,
                    'unit' => $material->unit,
                    'consumption_rate' => $item['consumption_rate'] ?? 0,
                    'waste_percent' => $item['waste_percent'] ?? 0,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'source' => $item['source'] ?? 'local',
                    'remark' => $item['remark'] ?? null,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->saveItemSizeMappings($bomItemId, $itemSizeSelections[$i] ?? []);
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
        $itemIds = DB::table('bom_items')->where('bom_header_id', $id)->pluck('id');
        DB::table('bom_item_customer_sizes')->whereIn('bom_item_id', $itemIds)->delete();
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
        $missingMaterialCodes = [];
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
                'category_id' => null,
            ];

            if (!empty($item['material_code'])) {
                $material = DB::table('materials')->where('internal_code', trim($item['material_code']))->first();
                if ($material) {
                    $item['material_code'] = $material->internal_code;
                    $item['material_name'] = $material->material_name;
                    $item['material_type'] = $material->material_type;
                    $item['category_id'] = $material->category_id;
                    $item['colour'] = $material->color;
                    $item['size'] = $material->size;
                    $item['unit'] = $material->unit;
                } else {
                    $missingMaterialCodes[] = trim($item['material_code']);
                }

                $parsedItems[] = $item;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($missingMaterialCodes) {
            return back()->with('error', 'These material codes do not exist in Material Master: ' . implode(', ', array_unique($missingMaterialCodes)));
        }

        return view('admin.bom.import-preview', [
            'parsedItems' => $parsedItems,
            'styles' => DB::table('ocs')->select('SNo', 'Sname', 'Customer')->distinct()->orderBy('SNo')->get(),
            'customers' => $this->customers(),
        ]);
    }

    public function importStore(Request $request)
    {
        // Store BOM from import preview
        return $this->store($request);
    }
}
