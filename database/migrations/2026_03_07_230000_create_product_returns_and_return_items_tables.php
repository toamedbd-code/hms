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
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->enum('return_type', ['customer', 'supplier'])->default('supplier');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->date('return_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'processed'])->default('pending');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('medicinesuppliers')
                ->nullOnDelete();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_return_id');
            $table->unsignedBigInteger('medicine_inventory_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('condition', ['good', 'damaged', 'expired'])->default('good');
            $table->timestamps();

            $table->foreign('product_return_id')
                ->references('id')
                ->on('product_returns')
                ->cascadeOnDelete();

            $table->foreign('medicine_inventory_id')
                ->references('id')
                ->on('medicineinventories')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('product_returns');
    }
};
