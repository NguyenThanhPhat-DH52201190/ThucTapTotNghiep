from pathlib import Path
import re

def change(path, fn):
    p=Path(path); s=p.read_text(encoding='utf-8'); p.write_text(fn(s), encoding='utf-8')

change('routes/web.php', lambda s: s.replace("    Route::get('/bom',", "    Route::get('/customer-styles/{style}/image', [\\App\\Http\\Controllers\\CustomerStyleController::class, 'image'])->middleware('role:admin,ie')->name('customer-styles.image');\n\n    Route::get('/bom',",1).replace("        Route::get('master-data/customers',", "        Route::get('master-data/customers/{customer}/styles', [\\App\\Http\\Controllers\\CustomerStyleController::class, 'index'])->name('customer-styles.index');\n        Route::post('master-data/customers/{customer}/styles', [\\App\\Http\\Controllers\\CustomerStyleController::class, 'save'])->name('customer-styles.store');\n        Route::put('master-data/customers/{customer}/styles/{style}', [\\App\\Http\\Controllers\\CustomerStyleController::class, 'save'])->name('customer-styles.update');\n        Route::get('master-data/customers',",1))
change('resources/views/admin/master-data/customers.blade.php', lambda s:s.replace('<td class="text-end"><button', '<td class="text-end"><a href="{{ route(\'admin.customer-styles.index\', $customer->id) }}" class="btn btn-sm btn-outline-primary me-1">Styles</a><button',1))
change('app/Http/Controllers/MasterDataController.php', lambda s:s.replace("        DB::table('customer_info')->where('id', $id)->delete();", "        if (DB::table('customer_styles')->where('customer_id', $id)->exists()) {\n            throw \\Illuminate\\Validation\\ValidationException::withMessages(['customer' => 'This customer has registered styles and cannot be deleted.']);\n        }\n        DB::table('customer_info')->where('id', $id)->delete();",1))

service='\\App\\Services\\CustomerStyleService'
for ctrl,table,code in [('OCSController','ocs','SNo'),('BOMController','bom_headers','style_no')]:
    def edit(s):
        s=s.replace(f"$path = DB::table('{table}')->where('id', $id)->value('image_path');", f"$path = app({service}::class)->imagePath('{table}', (int) $id);")
        if table=='ocs':
            s=s.replace("        $request->validate($this->orderRules", f"        app({service}::class)->apply($request, true);\n        $request->validate($this->orderRules")
            s=s.replace("->orderBy('ocs.CS', 'asc')", f"->selectRaw({service}::imageSql('ocs', 'SNo').' as image_path')\n            ->orderBy('ocs.CS', 'asc')",1)
            s=s.replace("if ($bom->customer_id && (int) $bom->customer_id !== $request->integer('customer_id'))", "if ((int) $bom->customer_id !== $request->integer('customer_id') || $bom->style_no !== $request->SNo)")
        else:
            s=s.replace("        $validated = $request->validate([", f"        app({service}::class)->apply($request);\n        $validated = $request->validate([")
            s=s.replace("    public function clone(Request $request, $id)\n    {", f"    public function clone(Request $request, $id)\n    {{\n        app({service}::class)->apply($request);")
            s=s.replace("->orderBy('created_at', 'desc')\n            ->paginate(20)", f"->select('bom_headers.*')->selectRaw({service}::imageSql('bom_headers', 'style_no').' as image_path')\n            ->orderBy('created_at', 'desc')\n            ->paginate(20)")
        return s
    change('app/Http/Controllers/'+ctrl+'.php',edit)
change('app/Http/Controllers/MasterPlanController.php', lambda s:s.replace("'ocs.image_path as ocs_image_path',", f"DB::raw({service}::imageSql('ocs', 'SNo').' as ocs_image_path'),"))
change('app/Http/Controllers/NormController.php', lambda s:s.replace("->selectRaw('CASE WHEN bom_headers.image_path IS NOT NULL THEN bom_headers.id WHEN template.image_path IS NOT NULL THEN template.id ELSE NULL END as bom_image_id')",f"->selectRaw({service}::imageSql('ocs', 'SNo').' as image_path')\n            ->selectRaw('CASE WHEN '.{service}::imageSql('bom_headers', 'style_no').' IS NOT NULL THEN bom_headers.id WHEN '.{service}::imageSql('template', 'style_no').' IS NOT NULL THEN template.id ELSE NULL END as bom_image_id')"))

partial="@include('admin.partials.customer-style-selector')"
for file in ['addocs','editocs']:
    def edit(s):
        s=s.replace("@include('admin.ocs.partials.image-field')", partial)
        s=re.sub(r'<input type="text" name="SNo"[^>]+>', '<select name="SNo" id="styleNo" class="form-select" data-customer-style data-current="{{ old(\'SNo\', $order->SNo ?? \'\') }}" required><option value="">-- Select Style --</option></select>',s)
        s=s.replace('data-style-no="{{ $bom->style_no }}"', 'data-customer="{{ $bom->customer_id }}" data-style-no="{{ $bom->style_no }}"')
        # Style name is canonical; BOM selection must not override it.
        s=re.sub(r'const bomHeader = document.getElementById\(\'bomHeader\'\);.*?syncStyleNameFromBom\(\);', '',s,flags=re.S)
        return s
    change('resources/views/admin/ocs/'+file+'.blade.php',edit)
for file in ['create','edit','import-preview','show']:
    def edit(s):
        s=re.sub(r"@include\('admin.partials.image-field',.*?\)\n",'',s)
        s=re.sub(r'<input[^>]*name="style_no"[^>]*>', '<select name="style_no" id="style_no" class="form-select" data-customer-style data-current="{{ old(\'style_no\', $bom->style_no ?? \'\') }}" required><option value="">-- Select Style --</option></select>',s)
        s=re.sub(r'<datalist id="styleList">.*?</datalist>','',s,flags=re.S)
        # Remove obsolete datalist lookup, retaining size handlers.
        s=re.sub(r"    const styleInput = document.getElementById\('style_no'\);.*?    \}\);", '', s,flags=re.S)
        s=s.replace("@section('content')", "@section('content')\n"+partial,1)
        return s
    change('resources/views/admin/bom/'+file+'.blade.php',edit)

change('tests/Support/CreatesLegacySchema.php', lambda s:s.replace("        $this->createUsersTable();", "        (require database_path('migrations/2026_09_22_000009_create_customer_styles.php'))->up();\n        $this->createUsersTable();",1))
