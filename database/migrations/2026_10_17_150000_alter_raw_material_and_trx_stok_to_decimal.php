<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->decimal('stok', 14, 4)->default(0)->change();
            $table->decimal('stok_minimum', 14, 4)->default(0)->change();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('quantity', 14, 4)->change();
            $table->decimal('balance_before', 14, 4)->change();
            $table->decimal('balance_after', 14, 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->integer('stok')->default(0)->change();
            $table->integer('stok_minimum')->default(0)->change();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->change();
            $table->decimal('balance_before', 12, 2)->change();
            $table->decimal('balance_after', 12, 2)->change();
        });
    }
};
