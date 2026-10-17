<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Unit::with('conversionsFrom.toUnit')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:units,name',
            'symbol' => 'nullable|string|max:10',
            'category' => 'nullable|in:weight,count,other',
        ]);

        $unit = Unit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil ditambahkan',
            'data' => $unit,
        ], 201);
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:units,name,' . $unit->id,
            'symbol' => 'nullable|string|max:10',
            'category' => 'nullable|in:weight,count,other',
        ]);

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil diperbarui',
            'data' => $unit,
        ]);
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil dihapus',
        ]);
    }
}
