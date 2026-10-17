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
        Schema::create('return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_transaction_id')->constrained('return_transactions')->cascadeOnDelete();
            $table->enum('item_type', ['raw_material','product']);
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 12, 2);
            $table->string('condition')->nullable(); // good, damaged
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_details');
    }
};
