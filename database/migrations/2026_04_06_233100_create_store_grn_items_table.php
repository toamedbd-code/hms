<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('store_grn_items')) {
            return;
        }

        Schema::create('store_grn_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_grn_id');
            $table->unsignedBigInteger('store_item_id');
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->foreign('store_grn_id')
                ->references('id')
                ->on('store_grns')
                ->onDelete('cascade');

            $table->foreign('store_item_id')
                ->references('id')
                ->on('store_items')
                ->onDelete('cascade');

            $table->index(['store_grn_id', 'store_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_grn_items');
    }
};
