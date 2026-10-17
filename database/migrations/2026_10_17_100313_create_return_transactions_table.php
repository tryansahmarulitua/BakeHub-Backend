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
        Schema::create('return_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->enum('return_type', ['purchase_return','sales_return']);
            $table->unsignedBigInteger('reference_id'); // sale_id or purchase_order_id
            $table->date('return_date');
            $table->enum('status', ['pending','approved','completed','rejected'])->default('pending');
            $table->text('reason');
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
        Schema::dropIfExists('return_transactions');
    }
};
