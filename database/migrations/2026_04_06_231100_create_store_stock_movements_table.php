<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('store_stock_movements')) {
            return;
        }

        Schema::create('store_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_item_id');
            $table->enum('movement_type', ['increase', 'decrease']);
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->text('reason')->nullable();
            $table->date('movement_date');
            $table->string('reference_no', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('store_item_id')
                ->references('id')
                ->on('store_items')
                ->onDelete('cascade');

            $table->index(['store_item_id', 'movement_date']);
            $table->index(['movement_type', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_movements');
    }
};
