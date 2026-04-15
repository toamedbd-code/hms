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
        Schema::create('medicine_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('medicinesuppliers')->cascadeOnDelete();
            $table->string('purchase_number')->unique();
            $table->date('purchase_date');
            $table->decimal('total_amount', 11, 2)->default(0);
            $table->decimal('paid_amount', 11, 2)->default(0);
            $table->decimal('due_amount', 11, 2)->default(0);
            $table->enum('status', ['pending', 'received', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_purchase_id')->constrained('medicine_purchases')->cascadeOnDelete();
            $table->foreignId('medicine_category_id')->constrained('medicinecategories')->cascadeOnDelete();
            $table->string('medicine_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_purchase_price', 11, 2)->default(0);
            $table->decimal('total_purchase_price', 11, 2)->default(0);
            $table->unsignedInteger('received_quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('medicine_purchases');
    }
};
