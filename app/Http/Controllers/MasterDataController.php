<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        DB::table('customer_info')->where('id', $id)->delete();
        return back()->with('success', 'Customer deleted. Existing OCS records keep their customer name.');
    }

    public function materials(Request $request)
    {
        $materials = DB::table('materials')
            ->leftJoin('material_vendors', 'materials.id', '=', 'material_vendors.material_id')
            ->select('materials.*', DB::raw('COUNT(material_vendors.id) as vendor_count'))
            ->when($request->filled('search'), fn ($query) => $query->where('materials.internal_code', 'like', '%' . $request->search . '%')->orWhere('materials.material_name', 'like', '%' . $request->search . '%'))
            ->groupBy('materials.id', 'materials.internal_code', 'materials.material_name', 'materials.color', 'materials.size', 'materials.unit', 'materials.material_type', 'materials.created_at', 'materials.updated_at')
            ->orderBy('materials.internal_code')->paginate(20)->withQueryString();
        $suppliers = DB::table('suppliers')->where('status', 'active')->orderBy('name')->get();
        $vendorMappings = DB::table('material_vendors')->join('materials', 'material_vendors.material_id', '=', 'materials.id')->join('suppliers', 'material_vendors.vendor_id', '=', 'suppliers.id')
            ->select('material_vendors.*', 'materials.internal_code', 'materials.material_name', 'suppliers.code as supplier_code', 'suppliers.name as supplier_name')->orderByDesc('material_vendors.is_default_vendor')->orderBy('materials.internal_code')->get();
        return view('admin.master-data.materials', compact('materials', 'suppliers', 'vendorMappings'));
    }

    private function materialRules(?int $id = null): array
    {
        return ['internal_code' => 'required|string|max:191|unique:materials,internal_code' . ($id ? ',' . $id : ''), 'material_name' => 'required|string|max:191', 'color' => 'nullable|string|max:100', 'size' => 'nullable|string|max:100', 'unit' => 'required|string|max:20', 'material_type' => 'required|string|max:30'];
    }

    public function storeMaterial(Request $request)
    {
        $data = $request->validate($this->materialRules());
        DB::table('materials')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Material added.');
    }

    public function updateMaterial(Request $request, int $id)
    {
        $data = $request->validate($this->materialRules($id));
        DB::table('materials')->where('id', $id)->update($data + ['updated_at' => now()]);
        return back()->with('success', 'Material updated.');
    }

    public function storeMaterialVendor(Request $request)
    {
        $data = $request->validate(['material_id' => 'required|exists:materials,id', 'vendor_id' => 'required|exists:suppliers,id', 'vendor_item_code' => 'nullable|string|max:191', 'unit_price' => 'required|numeric|min:0', 'lead_time_days' => 'required|integer|min:0', 'is_default_vendor' => 'nullable|boolean']);
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
