<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use App\Models\RecipeDetail;
use App\Services\RecipeCostService;
use Illuminate\Http\Request;

class RawMaterialController extends Controller
{
    public function __construct(private RecipeCostService $costService) {}

    public function index(Request $request)
    {
        $query = RawMaterial::with(['category', 'unit', 'supplier']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nama_bahan', 'like', "%{$search}%")
                  ->orWhere('kode_bahan', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bahan' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'harga_beli' => 'required|numeric|min:0',
            'stok' => 'required|numeric|min:0',
            'stok_minimum' => 'required|numeric|min:0',
            'tanggal_kedaluwarsa' => 'nullable|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'status' => 'in:aktif,nonaktif',
        ]);

        // Auto-generate kode bahan baku berdasarkan supplier_id & category_id
        $validated['kode_bahan'] = \App\Services\CodeGeneratorService::generateRawMaterialCode(
            $validated['supplier_id'],
            $validated['category_id']
        );

        $rawMaterial = RawMaterial::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bahan baku berhasil ditambahkan',
            'data' => $rawMaterial,
        ], 201);
    }

    public function show(RawMaterial $rawMaterial)
    {
        return response()->json([
            'success' => true,
            'data' => $rawMaterial->load(['category', 'unit', 'supplier']),
        ]);
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $validated = $request->validate([
            'nama_bahan' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'harga_beli' => 'required|numeric|min:0',
            'stok' => 'required|numeric|min:0',
            'stok_minimum' => 'required|numeric|min:0',
            'tanggal_kedaluwarsa' => 'nullable|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'status' => 'in:aktif,nonaktif',
        ]);

        // Jika supplier atau kategori berubah, regenerate kode
        if ($rawMaterial->supplier_id != $validated['supplier_id'] || $rawMaterial->category_id != $validated['category_id']) {
            $validated['kode_bahan'] = \App\Services\CodeGeneratorService::generateRawMaterialCode(
                $validated['supplier_id'],
                $validated['category_id']
            );
        }

        $rawMaterial->update($validated);

        // Update semua resep yang menggunakan bahan baku ini agar harga modal selalu akurat
        $affectedRecipes = \App\Models\Recipe::whereHas('details', fn($q) => $q->where('raw_material_id', $rawMaterial->id))->get();
        foreach ($affectedRecipes as $recipe) {
            $this->costService->calculateAndUpdate($recipe);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bahan baku berhasil diperbarui',
            'data' => $rawMaterial,
        ]);
    }

    public function destroy(RawMaterial $rawMaterial)
    {
        try {
            $rawMaterial->delete();
            return response()->json(['success' => true, 'message' => 'Bahan baku berhasil dihapus']);
        } catch (\Illuminate\Database\QueryException $e) {
            // Ada relasi — nonaktifkan saja
            $rawMaterial->update(['status' => 'nonaktif']);
            return response()->json([
                'success' => true,
                'message' => 'Bahan baku memiliki transaksi terkait, status diubah menjadi Nonaktif.',
                'deactivated' => true,
            ]);
        }
    }
}
