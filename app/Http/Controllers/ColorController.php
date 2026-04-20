<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ColorController extends Controller
{
    public function index()
    {
        $colors = DB::table('colors')
            ->orderBy('name')
            ->get();

        return view('admin.masterplan.colors.index', compact('colors'));
    }

    public function create()
    {
        return view('admin.masterplan.colors.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:colors,name',
            'hex_code' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}){1,2}$/'],
            'cate' => 'required|in:GSV,Subcon',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            DB::table('colors')->insert([
                'name' => trim($validated['name']),
                'hex_code' => strtoupper($validated['hex_code']),
                'cate' => $validated['cate'],
                'is_active' => $request->boolean('is_active'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.colors.index')
                ->with('success', 'Color added successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to create color record', [
                'message' => $e->getMessage(),
                'input' => $request->except(['_token']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to save color. Please try again.');
        }
    }

    public function edit(string $id)
    {
        $color = DB::table('colors')->where('id', $id)->first();

        if (!$color) {
            return redirect()->route('admin.colors.index')
                ->with('error', 'Color not found.');
        }

        return view('admin.masterplan.colors.edit', compact('color'));
    }

    public function update(Request $request, string $id)
    {
        $color = DB::table('colors')->where('id', $id)->first();

        if (!$color) {
            return redirect()->route('admin.colors.index')
                ->with('error', 'Color not found.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:colors,name,' . $id,
            'hex_code' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}){1,2}$/'],
            'cate' => 'required|in:GSV,Subcon',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            DB::table('colors')->where('id', $id)->update([
                'name' => trim($validated['name']),
                'hex_code' => strtoupper($validated['hex_code']),
                'cate' => $validated['cate'],
                'is_active' => $request->boolean('is_active'),
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.colors.index')
                ->with('success', 'Color updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to update color record', [
                'message' => $e->getMessage(),
                'id' => $id,
                'input' => $request->except(['_token', '_method']),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update color. Please try again.');
        }
    }

    public function destroy(string $id)
    {
        try {
            $deleted = DB::table('colors')->where('id', $id)->delete();

            if (!$deleted) {
                return redirect()->route('admin.colors.index')
                    ->with('error', 'Color not found.');
            }

            return redirect()->route('admin.colors.index')
                ->with('success', 'Color deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to delete color record', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return redirect()->route('admin.colors.index')
                ->with('error', 'Unable to delete color. Please try again.');
        }
    }
}
