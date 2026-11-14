<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reagent_test_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_item_id')->constrained('store_items')->cascadeOnDelete();
            $table->unsignedBigInteger('test_id')->index();
            $table->decimal('quantity_per_test', 14, 2);
            $table->timestamps();
            $table->unique(['store_item_id', 'test_id']);
        });

        Schema::create('reagent_bill_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_item_id')->index();
            $table->unsignedBigInteger('store_item_id')->index();
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->decimal('quantity', 14, 2);
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['bill_item_id', 'reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reagent_bill_consumptions');
        Schema::dropIfExists('reagent_test_usages');
    }
};