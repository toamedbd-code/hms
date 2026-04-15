<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('stock_adjustments')) {
            return;
        }

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_inventory_id');
            $table->enum('adjustment_type', ['increase', 'decrease']);
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->text('reason')->nullable();
            $table->date('adjustment_date');
            $table->timestamps();

            $table->foreign('medicine_inventory_id')
                ->references('id')
                ->on('medicineinventories')
                ->onDelete('cascade');

            $table->index(['medicine_inventory_id', 'adjustment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
