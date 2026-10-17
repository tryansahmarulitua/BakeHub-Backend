<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'unit']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nama_produk', 'like', "%{$search}%")
                  ->orWhere('kode_produk', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_produk' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'harga_jual' => 'required|numeric|min:0',
            'harga_modal' => 'required|numeric|min:0',
            'stok' => 'required|numeric|min:0',
            'tanggal_kedaluwarsa' => 'nullable|date',
            'status' => 'in:aktif,nonaktif',
        ]);

        // Auto-generate kode produk berdasarkan category_id
        $validated['kode_produk'] = \App\Services\CodeGeneratorService::generateProductCode($validated['category_id']);

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product,
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load(['category', 'unit']),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'nama_produk' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'harga_jual' => 'required|numeric|min:0',
            'harga_modal' => 'required|numeric|min:0',
            'stok' => 'required|numeric|min:0',
            'tanggal_kedaluwarsa' => 'nullable|date',
            'status' => 'in:aktif,nonaktif',
        ]);

        // Jika kategori berubah, regenerate kode
        if ($product->category_id != $validated['category_id']) {
            $validated['kode_produk'] = \App\Services\CodeGeneratorService::generateProductCode($validated['category_id']);
        }

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data' => $product,
        ]);
    }

    public function destroy(Product $product)
    {
        try {
            $product->delete();
            return response()->json(['success' => true, 'message' => 'Produk berhasil dihapus']);
        } catch (\Illuminate\Database\QueryException $e) {
            // Ada relasi — nonaktifkan saja
            $product->update(['status' => 'nonaktif']);
            return response()->json([
                'success' => true,
                'message' => 'Produk memiliki transaksi terkait, status diubah menjadi Nonaktif.',
                'deactivated' => true,
            ]);
        }
    }
}
