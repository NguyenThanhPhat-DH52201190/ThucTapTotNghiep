<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerStyleController extends Controller
{
    public function index(Request $request, int $customer)
    {
        $customer = DB::table('customer_info')->where('id', $customer)->first();
        abort_unless($customer, 404);
        $styles = DB::table('customer_styles')->where('customer_id', $customer->id)
            ->when($request->filled('search'), fn ($q) => $q->where('style_no', 'like', '%'.$request->search.'%'))
            ->orderBy('style_no')->paginate(20)->withQueryString();
        return view('admin.master-data.customer-styles', compact('customer', 'styles'));
    }

    public function save(Request $request, int $customer, ?int $style = null)
    {
        abort_unless(DB::table('customer_info')->where('id', $customer)->exists(), 404);
        $current = $style ? DB::table('customer_styles')->where('customer_id', $customer)->where('id', $style)->first() : null;
        if ($style) abort_unless($current, 404);
        $request->merge(['style_no' => trim((string) $request->style_no)]);
        $data = $request->validate([
            'style_no' => ['required', 'string', 'max:191', Rule::unique('customer_styles')->where('customer_id', $customer)->ignore($style)],
            'style_name' => 'required|string|max:191',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);
        if ($current && $current->style_no !== $data['style_no'] && $this->inUse($current)) {
            throw ValidationException::withMessages(['style_no' => 'This style is used by OCS or BOM. Keep its code and edit the name or image.']);
        }
        $path = null;
        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('customer-style-images', 'local');
                if (!$path) throw new \RuntimeException('Unable to store style image.');
            }
            unset($data['image']);
            $data += ['customer_id' => $customer, 'image_path' => $path ?: ($current->image_path ?? null), 'updated_at' => now()];
            DB::transaction(function () use ($style, $data) {
                if ($style) DB::table('customer_styles')->where('id', $style)->update($data);
                else DB::table('customer_styles')->insert($data + ['created_at' => now()]);
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($path && !empty($current->image_path)) Storage::disk('local')->delete($current->image_path);
        return redirect()->route('admin.customer-styles.index', $customer)->with('success', 'Style saved. Linked CU images use this style image.');
    }

    private function inUse(object $style): bool
    {
        return DB::table('ocs')->where('customer_id', $style->customer_id)->where('SNo', $style->style_no)->exists()
            || DB::table('bom_headers')->where('customer_id', $style->customer_id)->where('style_no', $style->style_no)->exists();
    }

    public function image(int $style)
    {
        $path = DB::table('customer_styles')->where('id', $style)->value('image_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }
}
