<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_product_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 60)->unique();
            $table->foreignId('store_supplier_id')->constrained('store_suppliers')->restrictOnDelete();
            $table->date('return_date');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('reason', 1000);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['return_date', 'store_supplier_id'], 'store_product_returns_date_supplier_idx');
        });

        Schema::create('store_product_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_product_return_id')->constrained('store_product_returns')->cascadeOnDelete();
            $table->foreignId('store_item_id')->constrained('store_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->unique(['store_product_return_id', 'store_item_id'], 'store_product_return_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_return_items');
        Schema::dropIfExists('store_product_returns');
    }
};
