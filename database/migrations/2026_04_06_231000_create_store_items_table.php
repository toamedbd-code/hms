<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('store_items')) {
            return;
        }

        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 50)->nullable()->unique();
            $table->string('item_name');
            $table->string('category', 120)->nullable();
            $table->string('unit', 50)->default('pcs');
            $table->decimal('reorder_level', 12, 2)->default(10);
            $table->decimal('current_stock', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'item_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_items');
    }
};
