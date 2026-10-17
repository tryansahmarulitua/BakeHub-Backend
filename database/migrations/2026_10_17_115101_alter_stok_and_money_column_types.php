<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubah tipe kolom:
 *  - stok & stok_minimum → integer (tidak ada pecahan)
 *  - semua field uang sudah decimal(14,2) — tidak perlu diubah
 *  - quantity di transaksi tetap decimal (bahan baku bisa pecahan)
 */
return new class extends Migration
{
    public function up(): void
    {
        // raw_materials: stok & stok_minimum → integer
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->integer('stok')->default(0)->change();
            $table->integer('stok_minimum')->default(0)->change();
        });

        // products: stok → integer
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stok')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->decimal('stok', 12, 2)->default(0)->change();
            $table->decimal('stok_minimum', 12, 2)->default(0)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stok', 12, 2)->default(0)->change();
        });
    }
};
