<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Customer;

class CodeGeneratorService
{
    /**
     * Generate kode produk otomatis (5 digit)
     * Format: 1[category_id 1 digit][urutan 001-999]
     * Contoh: 11001 (tipe 1, kategori 1, urutan 001)
     */
    public static function generateProductCode(int $categoryId): string
    {
        $categoryDigit = (string) ($categoryId % 10);
        $count = Product::where('category_id', $categoryId)->count();
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        return '1' . $categoryDigit . $sequence;
    }

    /**
     * Generate kode bahan baku otomatis (5 digit)
     * Digit 1: 2 (Bahan Baku)
     * Digit 2: diambil dari kode supplier, yaitu angka ke-2 dari kiri kode supplier
     * Digit 3: ID Kategori (1 digit)
     * Digit 4-5: Nomor urut (2 digit)
     * Contoh supplier 30001 -> digit ke-2 = 0, kode bahan baku = 21001
     */
    public static function generateRawMaterialCode(int $supplierId, int $categoryId): string
    {
        $supplier = Supplier::find($supplierId);
        $supplierDigit = '0';

        if ($supplier && is_string($supplier->kode_supplier) && strlen($supplier->kode_supplier) >= 2) {
            $supplierDigit = (string) $supplier->kode_supplier[1];
        }

        $categoryDigit = (string) ($categoryId % 10);

        $count = RawMaterial::where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->count();
        $sequence = str_pad($count + 1, 2, '0', STR_PAD_LEFT);

        return '2' . $supplierDigit . $categoryDigit . $sequence;
    }

    /**
     * Generate kode supplier otomatis
     * Format: 3[urutan 0000-9999]
     * Contoh: 30001, 30002, ..., 39999
     */
    public static function generateSupplierCode(): string
    {
        $count = Supplier::count();
        $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        return '3' . $sequence;
    }

    /**
     * Generate kode customer otomatis
     * Format: 4[tipe][urutan 000-999]
     * Tipe: umum=1, member=2, grosir=3, reseller=4
     * Contoh: 41001 (umum), 42001 (member), 43001 (grosir), 44001 (reseller)
     */
    public static function generateCustomerCode(string $type = 'umum'): string
    {
        $typeMap = [
            'umum'     => 1,
            'member'   => 2,
            'grosir'   => 3,
            'reseller' => 4,
        ];

        $digitTipe = $typeMap[$type] ?? 1;

        $count = Customer::where('tipe_customer', $type)->count();
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return '4' . $digitTipe . $sequence;
    }
}
