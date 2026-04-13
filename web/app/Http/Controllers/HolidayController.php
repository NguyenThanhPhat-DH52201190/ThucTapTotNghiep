<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $holidays = DB::table('holidays')->orderBy('holiday')->get();
        return view('admin.masterplan.holiday', compact('holidays'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.masterplan.holiday_create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $request->validate([
            'holiday' => 'required|date|unique:holidays,holiday',
            'name' => 'nullable|string|max:255'
        ]);

        DB::table('holidays')->insert([
            'holiday' => $request->holiday,
            'name' => $request->name,
        ]);

        return redirect()->route('admin.holidays.index')
            ->with('success', 'Added successfully');
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $holiday = DB::table('holidays')->where('id', $id)->first();
        return view('admin.masterplan.holiday_edit', compact('holiday'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'holiday' => 'required|date|unique:holidays,holiday,' . $id,
            'name' => 'nullable|string|max:255'
        ]);

        DB::table('holidays')->where('id', $id)->update([
            'holiday' => $request->holiday,
            'name' => $request->name,
            'updated_at' => now(),
        ]);

        cache()->forget('holidays');

        return redirect()->route('admin.holidays.index')
            ->with('success', 'Updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('holidays')->where('id', $id)->delete();

        cache()->forget('holidays');

        return redirect()->back()->with('success', 'Deleted successfully');
    }
}
