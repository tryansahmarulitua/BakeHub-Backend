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

it('returns sales report data with sales transactions and summary', function () {
    $role = Role::create(['name' => 'Admin']);
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'role_id' => $role->id,
    ]);

    $unit = Unit::create(['name' => 'Pcs', 'symbol' => 'pcs', 'category' => 'count']);
    $cat  = Category::create(['name' => 'Roti', 'type' => 'produk']);

    $prod = Product::create([
        'kode_produk' => '10101',
        'nama_produk' => 'Roti Manis',
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'harga_jual' => 10000,
        'harga_modal' => 5000,
        'stok' => 20,
        'status' => 'aktif',
    ]);

    $sale = Sale::create([
        'invoice_number' => 'INV-20261101-0001',
        'sale_date' => now()->format('Y-m-d'),
        'subtotal' => 20000,
        'discount' => 0,
        'tax' => 2400,
        'total' => 22400,
        'payment_amount' => 25000,
        'change_amount' => 2600,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'status' => 'completed',
        'created_by' => $user->id,
    ]);

    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_id' => $prod->id,
        'quantity' => 2,
        'unit_price' => 10000,
        'discount' => 0,
        'subtotal' => 20000,
    ]);

    $res = $this->actingAs($user, 'sanctum')->getJson('/api/reports/sales');
    $res->assertOk();

    expect($res->json('data.sales'))->toHaveCount(1);
    expect($res->json('data.summary.total_transactions'))->toBe(1);
    expect($res->json('data.summary.total_revenue'))->toBe('22400.00');

    // Also with empty strings from_date and to_date
    $resEmpty = $this->actingAs($user, 'sanctum')->getJson('/api/reports/sales?from_date=&to_date=');
    $resEmpty->assertOk();
    expect($resEmpty->json('data.sales'))->toHaveCount(1);
});
