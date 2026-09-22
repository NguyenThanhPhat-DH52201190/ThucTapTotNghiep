<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function customers(Request $request)
    {
        $customers = DB::table('customer_info')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . $request->search . '%')->orWhere('brand', 'like', '%' . $request->search . '%'))
            ->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.master-data.customers', compact('customers'));
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:191', 'address' => 'nullable|string', 'contact' => 'nullable|string|max:191', 'tel' => 'nullable|string|max:50', 'email' => 'nullable|email|max:191', 'brand' => 'nullable|string|max:191']);
        DB::table('customer_info')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Customer added.');
    }

    public function updateCustomer(Request $request, int $id)
    {
        $data = $request->validate(['name' => 'required|string|max:191', 'address' => 'nullable|string', 'contact' => 'nullable|string|max:191', 'tel' => 'nullable|string|max:50', 'email' => 'nullable|email|max:191', 'brand' => 'nullable|string|max:191']);
        DB::table('customer_info')->where('id', $id)->update($data + ['updated_at' => now()]);
        return back()->with('success', 'Customer updated.');
    }

    public function destroyCustomer(int $id)
    {
        if (DB::table('customer_styles')->where('customer_id', $id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['customer' => 'This customer has registered styles and cannot be deleted.']);
        }
        DB::table('customer_info')->where('id', $id)->delete();
        return back()->with('success', 'Customer deleted. Existing OCS records keep their customer name.');
    }

    public function customerSizes()
    {
        $customers = DB::table('customer_info')->orderBy('name')->get();
        $sizes = DB::table('customer_sizes')->orderBy('sort_order')->orderBy('size_name')->get()->groupBy('customer_id');
        return view('admin.master-data.customer-sizes', compact('customers', 'sizes'));
    }

    public function saveCustomerSizes(Request $request, int $id)
    {
        abort_unless(DB::table('customer_info')->where('id', $id)->exists(), 404);
        $data = $request->validate(['sizes' => 'required|string|max:2000']);
        $sizes = collect(preg_split('/[,\r\n]+/', $data['sizes']))
            ->map(fn ($size) => trim($size))->filter()->unique(fn ($size) => mb_strtolower($size))->values();
        if ($sizes->isEmpty()) return back()->with('error', 'Enter at least one size.');

        DB::transaction(function () use ($id, $sizes) {
            $existing = DB::table('customer_sizes')->where('customer_id', $id)->get()
                ->keyBy(fn ($row) => mb_strtolower(trim($row->size_name)));
            $submittedKeys = $sizes->map(fn ($size) => mb_strtolower($size));
            $removedIds = $existing->except($submittedKeys)->pluck('id');
            if ($removedIds->isNotEmpty() && DB::table('bom_item_customer_sizes')->whereIn('customer_size_id', $removedIds)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sizes' => ['A size cannot be removed while it is assigned to a BOM item. Update the BOM first.'],
                ]);
            }

            DB::table('customer_sizes')->whereIn('id', $removedIds)->delete();
            foreach ($sizes as $index => $size) {
                $current = $existing->get(mb_strtolower($size));
                if ($current) {
                    DB::table('customer_sizes')->where('id', $current->id)->update([
                        'size_name' => $size, 'sort_order' => $index + 1, 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('customer_sizes')->insert([
                        'customer_id' => $id, 'size_name' => $size, 'sort_order' => $index + 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
        return back()->with('success', 'Customer size breakdown saved.');
    }

    public function materials(Request $request)
    {
        $materials = DB::table('materials')
            ->leftJoin('material_vendors', 'materials.id', '=', 'material_vendors.material_id')
            ->leftJoin('material_categories', 'materials.category_id', '=', 'material_categories.id')
            ->leftJoin('material_subcategories', 'materials.subcategory_id', '=', 'material_subcategories.id')
            ->select('materials.*', 'material_categories.name as category_name', 'material_subcategories.name as subcategory_name', DB::raw('COUNT(material_vendors.id) as vendor_count'))
            ->when($request->filled('category_id'), fn ($query) => $query->where('materials.category_id', $request->integer('category_id')))
            ->when($request->filled('subcategory_id'), fn ($query) => $query->where('materials.subcategory_id', $request->integer('subcategory_id')))
            ->groupBy('materials.id', 'materials.internal_code', 'materials.old_code', 'materials.material_name', 'materials.color', 'materials.size', 'materials.unit', 'materials.material_type', 'materials.category_id', 'materials.subcategory_id', 'materials.created_at', 'materials.updated_at', 'materials.image_path', 'material_categories.name', 'material_subcategories.name')
            ->orderBy('material_categories.name')
            ->orderBy('material_subcategories.name')
            ->orderBy('materials.internal_code')
            ->paginate(20)->withQueryString();
        $suppliers = DB::table('suppliers')->where('status', 'active')->orderBy('name')->get();
        $vendorMappings = DB::table('material_vendors')->join('materials', 'material_vendors.material_id', '=', 'materials.id')->join('suppliers', 'material_vendors.vendor_id', '=', 'suppliers.id')
            ->select('material_vendors.*', 'materials.internal_code', 'materials.material_name', 'suppliers.code as supplier_code', 'suppliers.name as supplier_name')
            ->when($request->filled('mapping_category_id'), fn ($query) => $query->where('materials.category_id', $request->integer('mapping_category_id')))
            ->when($request->filled('mapping_subcategory_id'), fn ($query) => $query->where('materials.subcategory_id', $request->integer('mapping_subcategory_id')))
            ->orderByDesc('material_vendors.is_default_vendor')->orderBy('materials.internal_code')->orderBy('material_vendors.id')
            ->paginate(20, ['*'], 'mapping_page')->withQueryString()->fragment('materialMappings');
        $categories = DB::table('material_categories')->orderBy('name')->get();
        $subcategories = DB::table('material_subcategories')->orderBy('name')->get();
        return view('admin.master-data.materials', compact('materials', 'suppliers', 'vendorMappings', 'categories', 'subcategories'));
    }

    private function materialRules(?int $id = null): array
    {
        return ['internal_code' => 'required|string|max:191|unique:materials,internal_code' . ($id ? ',' . $id : ''), 'old_code' => 'nullable|string|max:191|unique:materials,old_code' . ($id ? ',' . $id : ''), 'material_name' => 'required|string|max:191', 'color' => 'nullable|string|max:100', 'size' => 'nullable|string|max:100', 'unit' => 'required|string|max:20', 'category_id' => 'required|exists:material_categories,id', 'subcategory_id' => ['nullable', Rule::exists('material_subcategories', 'id')->where(fn ($query) => $query->where('category_id', request('category_id')))]];
    }

    private function materialData(Request $request, ?int $id = null): array
    {
        $request->validate([
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);
        $data = $request->validate($this->materialRules($id));
        $data['material_type'] = DB::table('material_categories')->where('id', $data['category_id'])->value('slug');
        return $data;
    }

    public function storeMaterial(Request $request)
    {
        $data = $this->materialData($request);
        $this->saveMaterialImage($request, null, fn ($imagePath) => DB::table('materials')->insert($data + ['image_path' => $imagePath, 'created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'Material added.');
    }

    public function updateMaterial(Request $request, int $id)
    {
        abort_unless(DB::table('materials')->where('id', $id)->exists(), 404);
        $data = $this->materialData($request, $id);
        $this->saveMaterialImage($request, $id, function ($imagePath) use ($id, $data) {
            DB::table('materials')->where('id', $id)->update($data + ['image_path' => $imagePath, 'updated_at' => now()]);
            DB::table('bom_items')->where('material_id', $id)->update([
                'material_code' => $data['internal_code'], 'material_name' => $data['material_name'],
                'material_type' => $data['material_type'], 'colour' => $data['color'] ?? null,
                'size' => $data['size'] ?? null, 'unit' => $data['unit'], 'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Material updated.');
    }

    private function saveMaterialImage(Request $request, ?int $id, callable $save): void
    {
        $newPath = null;
        $oldPath = null;
        try {
            DB::transaction(function () use ($request, $id, $save, &$newPath, &$oldPath) {
                if ($id) {
                    $material = DB::table('materials')->where('id', $id)->lockForUpdate()->first();
                    abort_unless($material, 404);
                    $oldPath = $material->image_path;
                }
                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store('material-images/materials', 'local');
                    if (!$path) throw new \RuntimeException('Unable to save material image.');
                    $newPath = $path;
                }
                $save($newPath ?? $oldPath);
            });
        } catch (\Throwable $e) {
            $this->deleteMaterialImage($newPath);
            throw $e;
        }
        if ($newPath) $this->deleteMaterialImage($oldPath);
    }
    private function deleteMaterialImage(?string $path): void
    {
        if (!$path) return;
        try {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Unable to remove material image', ['path' => $path]);
        }
    }

    public function materialImage(int $id)
    {
        $path = DB::table('materials')->where('id', $id)->value('image_path');
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);
        return $disk->response($path, null, ['Cache-Control' => 'private, no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }
    public function storeMaterialCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100|unique:material_categories,name']);
        $slug = $this->uniqueCategorySlug($data['name']);
        DB::table('material_categories')->insert(['name' => $data['name'], 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Category added.');
    }

    public function updateMaterialCategory(Request $request, int $id)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('material_categories', 'name')->ignore($id)]]);
        $slug = $this->uniqueCategorySlug($data['name'], $id);
        DB::transaction(function () use ($id, $data, $slug) {
            DB::table('material_categories')->where('id', $id)->update(['name' => $data['name'], 'slug' => $slug, 'updated_at' => now()]);
            $materialIds = DB::table('materials')->where('category_id', $id)->pluck('id');
            DB::table('materials')->where('category_id', $id)->update(['material_type' => $slug, 'updated_at' => now()]);
            DB::table('bom_items')->whereIn('material_id', $materialIds)->update(['material_type' => $slug, 'updated_at' => now()]);
        });
        return back()->with('success', 'Category updated.');
    }

    public function destroyMaterialCategory(int $id)
    {
        if (DB::table('materials')->where('category_id', $id)->exists() || DB::table('material_subcategories')->where('category_id', $id)->exists()) {
            return back()->with('error', 'Cannot delete a category that contains materials or subcategories.');
        }
        $path = DB::table('material_categories')->where('id', $id)->value('image_path');
        DB::table('material_categories')->where('id', $id)->delete();
        $this->deleteMaterialImage($path);
        return back()->with('success', 'Category deleted.');
    }

    public function storeMaterialSubcategory(Request $request)
    {
        $data = $request->validate(['category_id' => 'required|exists:material_categories,id', 'name' => ['required', 'string', 'max:100', Rule::unique('material_subcategories', 'name')->where(fn ($query) => $query->where('category_id', $request->category_id))]]);
        DB::table('material_subcategories')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Subcategory added.');
    }

    public function updateMaterialSubcategory(Request $request, int $id)
    {
        $data = $request->validate(['category_id' => 'required|exists:material_categories,id', 'name' => ['required', 'string', 'max:100', Rule::unique('material_subcategories', 'name')->where(fn ($query) => $query->where('category_id', $request->category_id))->ignore($id)]]);
        DB::table('material_subcategories')->where('id', $id)->update($data + ['updated_at' => now()]);
        return back()->with('success', 'Subcategory updated.');
    }

    public function destroyMaterialSubcategory(int $id)
    {
        if (DB::table('materials')->where('subcategory_id', $id)->exists()) {
            return back()->with('error', 'Cannot delete a subcategory that is assigned to materials.');
        }
        $path = DB::table('material_subcategories')->where('id', $id)->value('image_path');
        DB::table('material_subcategories')->where('id', $id)->delete();
        $this->deleteMaterialImage($path);
        return back()->with('success', 'Subcategory deleted.');
    }

    private function uniqueCategorySlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 2;
        while (DB::table('material_categories')->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    public function storeMaterialVendor(Request $request)
    {
        $data = $request->validate(['material_id' => 'required|exists:materials,id', 'vendor_id' => 'required|exists:suppliers,id', 'vendor_item_code' => 'nullable|string|max:191', 'unit_price' => 'required|numeric|decimal:0,4|min:0', 'lead_time_days' => 'required|integer|min:0', 'is_default_vendor' => 'nullable|boolean']);
        DB::transaction(function () use ($data) {
            if (!empty($data['is_default_vendor'])) DB::table('material_vendors')->where('material_id', $data['material_id'])->update(['is_default_vendor' => false, 'updated_at' => now()]);
            DB::table('material_vendors')->updateOrInsert(['material_id' => $data['material_id'], 'vendor_id' => $data['vendor_id']], ['vendor_item_code' => $data['vendor_item_code'] ?? null, 'unit_price' => $data['unit_price'], 'lead_time_days' => $data['lead_time_days'], 'is_default_vendor' => !empty($data['is_default_vendor']), 'updated_at' => now(), 'created_at' => now()]);
        });
        return back()->with('success', 'Material–supplier mapping saved.');
    }

    public function destroyMaterialVendor(int $id)
    {
        DB::table('material_vendors')->where('id', $id)->delete();
        return back()->with('success', 'Material–supplier mapping deleted.');
    }
}
