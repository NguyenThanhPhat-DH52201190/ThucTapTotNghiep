<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaterialCopyController extends Controller
{
    public function create(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'required|integer|distinct|exists:materials,id',
        ]);
        $sources = DB::table('materials')->whereIn('id', $data['ids'])->orderBy('internal_code')->get();
        $categories = DB::table('material_categories')->orderBy('name')->get();
        $subcategories = DB::table('material_subcategories')->orderBy('name')->get();
        $rows = $sources->map(fn ($source) => [
            'source_id' => $source->id, 'internal_code' => '', 'old_code' => '',
            'material_name' => $source->material_name, 'category_id' => $source->category_id,
            'subcategory_id' => $source->subcategory_id, 'color' => $source->color,
            'size' => $source->size, 'unit' => $source->unit, 'copy_image' => (bool) $source->image_path,
        ])->all();
        return view('admin.master-data.copy-materials', compact('sources', 'categories', 'subcategories', 'rows'));
    }

    public function store(Request $request)
    {
        $rules = [
            'rows' => 'required|array|min:1|max:100',
            'rows.*' => 'required|array:source_id,internal_code,old_code,material_name,category_id,subcategory_id,color,size,unit,copy_image',
            'rows.*.source_id' => 'required|integer|exists:materials,id',
            'rows.*.internal_code' => 'required|string|max:191|distinct:ignore_case|unique:materials,internal_code',
            'rows.*.old_code' => 'nullable|string|max:191|distinct:ignore_case|unique:materials,old_code',
            'rows.*.material_name' => 'required|string|max:191',
            'rows.*.category_id' => 'required|integer|exists:material_categories,id',
            'rows.*.color' => 'nullable|string|max:100', 'rows.*.size' => 'nullable|string|max:100',
            'rows.*.unit' => 'required|string|max:20', 'rows.*.copy_image' => 'required|boolean',
        ];
        // Validate the parent before iterating user-supplied rows.
        $request->validate(['rows' => 'required|array|min:1|max:100', 'rows.*' => 'required|array']);
        foreach ($request->input('rows') as $index => $row) {
            $rules["rows.$index.subcategory_id"] = ['nullable', 'integer', Rule::exists('material_subcategories', 'id')->where('category_id', $row['category_id'] ?? null)];
        }
        $data = $request->validate($rules);
        $newImages = [];
        try {
            DB::transaction(function () use ($data, &$newImages) {
                $sources = DB::table('materials')->whereIn('id', array_column($data['rows'], 'source_id'))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $types = DB::table('material_categories')->pluck('slug', 'id');
                foreach ($data['rows'] as $index => $row) {
                    $source = $sources->get($row['source_id']);
                    if (!$source) throw ValidationException::withMessages(["rows.$index.source_id" => 'Source material no longer exists.']);
                    $imagePath = null;
                    if ($row['copy_image'] && $source->image_path) {
                        $disk = Storage::disk('local');
                        if (!$disk->exists($source->image_path)) {
                            throw ValidationException::withMessages(["rows.$index.copy_image" => 'Source image is unavailable. Uncheck Copy image to continue.']);
                        }
                        $imagePath = 'material-images/materials/'.Str::uuid().'.'.pathinfo($source->image_path, PATHINFO_EXTENSION);
                        $newImages[] = $imagePath;
                        if (!$disk->copy($source->image_path, $imagePath)) throw new \RuntimeException('Unable to copy material image.');
                    }
                    unset($row['source_id'], $row['copy_image']);
                    DB::table('materials')->insert($row + [
                        'image_path' => $imagePath, 'material_type' => $types[$row['category_id']],
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($newImages as $path) Storage::disk('local')->delete($path);
            if ($e instanceof \Illuminate\Database\UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['rows' => 'A material code was just used by another request. Check the codes and save again.']);
            }
            throw $e;
        }
        return redirect()->route('admin.master-data.materials')->with('success', count($data['rows']).' materials copied successfully.');
    }
}
