<?php

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the second digit of the supplier code for the raw material code', function () {
    $category = Category::create([
        'name' => 'Bahan Kering',
        'type' => 'bahan',
    ]);
    $unit = Unit::create([
        'name' => 'Kilogram',
    ]);
    $supplier = Supplier::create([
        'kode_supplier' => '30001',
        'nama_supplier' => 'Supplier A',
        'nama_pic' => 'Budi',
        'nomor_telepon' => '081234567890',
        'email' => 'supplier@example.com',
        'alamat' => 'Jakarta',
        'status' => 'aktif',
    ]);

    expect(CodeGeneratorService::generateRawMaterialCode($supplier->id, $category->id))->toBe('20101');
});

it('keeps a valid raw material code format even if previous code data is odd', function () {
    $category = Category::create([
        'name' => 'Bahan Cair',
        'type' => 'bahan',
    ]);
    $unit = Unit::create([
        'name' => 'Liter',
    ]);
    $supplier = Supplier::create([
        'kode_supplier' => '30002',
        'nama_supplier' => 'Supplier B',
        'nama_pic' => 'Ani',
        'nomor_telepon' => '081122334455',
        'email' => 'supplierb@example.com',
        'alamat' => 'Bandung',
        'status' => 'aktif',
    ]);

    RawMaterial::create([
        'kode_bahan' => 'X9999',
        'nama_bahan' => 'Minyak Goreng',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'harga_beli' => 18000,
        'stok' => 20,
        'stok_minimum' => 5,
        'tanggal_kedaluwarsa' => '2026-12-31',
        'supplier_id' => $supplier->id,
        'status' => 'aktif',
    ]);

    $code = CodeGeneratorService::generateRawMaterialCode($supplier->id, $category->id);

    expect($code)->toStartWith('2');
    expect(strlen($code))->toBe(5);
    expect($code[1])->toBe('0');
});

it('creates a supplier and a raw material in the same flow without errors', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'name' => 'Bahan Pokok',
        'type' => 'bahan',
    ]);
    $unit = Unit::create([
        'name' => 'Kilogram',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/suppliers', [
            'nama_supplier' => 'Supplier Baru',
            'nama_pic' => 'Budi',
            'nomor_telepon' => '08123456789',
            'email' => 'supplierbaru@example.com',
            'alamat' => 'Jakarta',
            'status' => 'aktif',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $supplierId = Supplier::query()->latest('id')->first()->id;

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/raw-materials', [
            'nama_bahan' => 'Tepung Terigu',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'harga_beli' => 12000,
            'stok' => 10,
            'stok_minimum' => 2,
            'tanggal_kedaluwarsa' => '2026-12-31',
            'supplier_id' => $supplierId,
            'status' => 'aktif',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.supplier_id', $supplierId);
});

it('loads the inventory stock card without item filters', function () {
    $user = User::factory()->create();

    InventoryTransaction::create([
        'transaction_number' => 'STK-001',
        'item_type' => 'raw_material',
        'item_id' => 1,
        'transaction_type' => 'PURCHASE',
        'quantity' => 10,
        'balance_before' => 0,
        'balance_after' => 10,
        'reference_type' => 'purchase',
        'reference_id' => 1,
        'transaction_date' => '2026-10-01',
        'created_by' => $user->id,
    ]);

    InventoryTransaction::create([
        'transaction_number' => 'STK-002',
        'item_type' => 'product',
        'item_id' => 2,
        'transaction_type' => 'SALE',
        'quantity' => 2,
        'balance_before' => 8,
        'balance_after' => 6,
        'reference_type' => 'sale',
        'reference_id' => 2,
        'transaction_date' => '2026-10-02',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/inventory/stock-card')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.data.0.item_type', 'raw_material')
        ->assertJsonPath('data.data.1.item_type', 'product');
});
