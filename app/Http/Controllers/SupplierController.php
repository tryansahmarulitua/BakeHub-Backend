<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nama_supplier', 'like', "%{$search}%")
                  ->orWhere('kode_supplier', 'like', "%{$search}%")
                  ->orWhere('nama_pic', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_supplier' => 'required|string',
            'nama_pic' => 'required|string',
            'nomor_telepon' => 'required|string',
            'email' => 'required|email',
            'alamat' => 'required|string',
            'status' => 'in:aktif,nonaktif',
        ]);

        $validated['kode_supplier'] = \App\Services\CodeGeneratorService::generateSupplierCode();

        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier berhasil ditambahkan',
            'data' => $supplier,
        ], 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json([
            'success' => true,
            'data' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'nama_supplier' => 'required|string',
            'nama_pic' => 'required|string',
            'nomor_telepon' => 'required|string',
            'email' => 'required|email',
            'alamat' => 'required|string',
            'status' => 'in:aktif,nonaktif',
        ]);

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier berhasil diperbarui',
            'data' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier berhasil dihapus',
        ]);
    }
}
