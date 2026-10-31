<?php

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes details with raw material and unit in purchase order index and show', function () {
    $role = Role::create(['name' => 'Admin']);
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'role_id' => $role->id,
    ]);

    $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'category' => 'weight']);
    $cat  = Category::create(['name' => 'Bahan Baku', 'type' => 'bahan']);
    $sup  = Supplier::create([
        'kode_supplier' => '30001',
        'nama_supplier' => 'Supplier Jaya',
        'nama_pic' => 'Andi',
        'nomor_telepon' => '0812345678',
        'email' => 'jaya@test.com',
        'alamat' => 'Jl. Merdeka',
        'status' => 'aktif',
    ]);

    $mat = RawMaterial::create([
        'kode_bahan' => '20101',
        'nama_bahan' => 'Tepung Terigu',
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'supplier_id' => $sup->id,
        'harga_beli' => 15000,
        'stok' => 50,
        'status' => 'aktif',
    ]);

    $po = PurchaseOrder::create([
        'nomor_po' => 'PO-20261031-0001',
        'supplier_id' => $sup->id,
        'tanggal_po' => '2026-10-31',
        'status' => 'draft',
        'subtotal' => 150000,
        'total' => 150000,
        'created_by' => $user->id,
    ]);

    PurchaseOrderDetail::create([
        'purchase_order_id' => $po->id,
        'raw_material_id' => $mat->id,
        'quantity' => 10,
        'unit_price' => 15000,
        'subtotal' => 150000,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/purchase-orders');
    $response->assertOk();
    $data = $response->json('data.data');

    expect($data)->toHaveCount(1);
    expect($data[0]['details'])->toHaveCount(1);
    expect($data[0]['details'][0]['raw_material']['nama_bahan'])->toBe('Tepung Terigu');

    $showResponse = $this->actingAs($user, 'sanctum')->getJson("/api/purchase-orders/{$po->id}");
    $showResponse->assertOk();
    expect($showResponse->json('data.details')[0]['raw_material']['nama_bahan'])->toBe('Tepung Terigu');
});
