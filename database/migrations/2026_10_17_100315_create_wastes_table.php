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
        Schema::create('wastes', function (Blueprint $table) {
            $table->id();
            $table->string('waste_number')->unique();
            $table->date('waste_date');
            $table->enum('item_type', ['raw_material','product']);
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 12, 2);
            $table->enum('reason', ['expired','damaged','burned','failed_production','unsold','spill','other']);
            $table->decimal('estimated_loss', 14, 2)->default(0);
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
        Schema::dropIfExists('wastes');
    }
};
