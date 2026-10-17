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
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('biaya_bahan', 14, 2)->default(0)->after('output_quantity');
            $table->decimal('biaya_kemasan', 14, 2)->default(0)->after('biaya_bahan');
            $table->decimal('overhead_percent', 5, 2)->default(10)->after('biaya_kemasan');
            $table->decimal('biaya_overhead', 14, 2)->default(0)->after('overhead_percent');
            $table->decimal('total_biaya_produksi', 14, 2)->default(0)->after('biaya_overhead');
            $table->decimal('harga_modal_per_unit', 14, 2)->default(0)->after('total_biaya_produksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn([
                'biaya_bahan',
                'biaya_kemasan',
                'overhead_percent',
                'biaya_overhead',
                'total_biaya_produksi',
                'harga_modal_per_unit',
            ]);
        });
    }
};
