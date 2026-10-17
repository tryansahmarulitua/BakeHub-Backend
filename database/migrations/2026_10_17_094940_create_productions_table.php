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
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_produksi')->unique();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('recipe_id')->constrained('recipes');
            $table->decimal('quantity', 12, 2);
            $table->date('production_date');
            $table->enum('status', ['draft','planned','processing','completed','cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
