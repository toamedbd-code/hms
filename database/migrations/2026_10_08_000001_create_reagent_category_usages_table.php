<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reagent_category_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_item_id')->constrained('store_items')->cascadeOnDelete();
            $table->string('category_key', 100);
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->decimal('quantity_per_test', 14, 2);
            $table->timestamps();
            $table->unique(['store_item_id', 'category_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reagent_category_usages');
    }
};
