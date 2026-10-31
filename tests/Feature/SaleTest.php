<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes details with product and unit in sales index and show', function () {
    $role = Role::create(['name' => 'Kasir']);
    $user = User::create([
        'name' => 'Kasir',
        'email' => 'kasir@test.com',
        'password' => bcrypt('password'),
        'role_id' => $role->id,
    ]);

    $unit = Unit::create(['name' => 'Pcs', 'symbol' => 'pcs', 'category' => 'count']);
    $cat  = Category::create(['name' => 'Roti Manis', 'type' => 'produk']);

    $prod = Product::create([
        'kode_produk' => '10101',
        'nama_produk' => 'Roti Coklat',
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'harga_jual' => 8000,
        'harga_modal' => 4000,
        'stok' => 50,
        'status' => 'aktif',
    ]);

    $sale = Sale::create([
        'invoice_number' => 'INV-20261101-0001',
        'sale_date' => '2026-11-01',
        'subtotal' => 16000,
        'discount' => 0,
        'tax' => 1920,
        'total' => 17920,
        'payment_amount' => 20000,
        'change_amount' => 2080,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'status' => 'completed',
        'created_by' => $user->id,
    ]);

    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_id' => $prod->id,
        'quantity' => 2,
        'unit_price' => 8000,
        'discount' => 0,
        'subtotal' => 16000,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/sales');
    $response->assertOk();
    $data = $response->json('data.data');

    expect($data)->toHaveCount(1);
    expect($data[0]['details'])->toHaveCount(1);
    expect($data[0]['details'][0]['product']['nama_produk'])->toBe('Roti Coklat');

    $showResponse = $this->actingAs($user, 'sanctum')->getJson("/api/sales/{$sale->id}");
    $showResponse->assertOk();
    expect($showResponse->json('data.details')[0]['product']['nama_produk'])->toBe('Roti Coklat');
});
