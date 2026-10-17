<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode_customer', 'like', "%{$search}%")
                  ->orWhere('nomor_telepon', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 10)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string',
            'nomor_telepon' => 'required|string',
            'email' => 'required|email',
            'alamat' => 'required|string',
            'tipe_customer' => 'nullable|in:umum,member,reseller,grosir',
        ]);

        $tipe = $validated['tipe_customer'] ?? 'umum';
        $validated['tipe_customer'] = $tipe;
        $validated['kode_customer'] = \App\Services\CodeGeneratorService::generateCustomerCode($tipe);

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer berhasil ditambahkan',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer)
    {
        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'nama' => 'required|string',
            'nomor_telepon' => 'required|string',
            'email' => 'required|email',
            'alamat' => 'required|string',
            'tipe_customer' => 'nullable|in:umum,member,reseller,grosir',
        ]);

        $tipe = $validated['tipe_customer'] ?? $customer->tipe_customer;
        $validated['tipe_customer'] = $tipe;

        // Jika tipe berubah, regenerate kode
        if ($customer->tipe_customer !== $tipe) {
            $validated['kode_customer'] = \App\Services\CodeGeneratorService::generateCustomerCode($tipe);
        }

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer berhasil diperbarui',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer berhasil dihapus',
        ]);
    }
}
