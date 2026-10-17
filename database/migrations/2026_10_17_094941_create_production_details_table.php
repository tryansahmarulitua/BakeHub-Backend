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
        Schema::create('production_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('raw_materials');
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('quantity_needed', 12, 2);
            $table->decimal('quantity_used', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_details');
    }
};
