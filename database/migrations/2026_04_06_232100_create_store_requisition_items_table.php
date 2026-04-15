<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('store_requisition_items')) {
            return;
        }

        Schema::create('store_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_requisition_id');
            $table->unsignedBigInteger('store_item_id');
            $table->decimal('requested_qty', 14, 2);
            $table->decimal('issued_qty', 14, 2)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->foreign('store_requisition_id')
                ->references('id')
                ->on('store_requisitions')
                ->onDelete('cascade');

            $table->foreign('store_item_id')
                ->references('id')
                ->on('store_items')
                ->onDelete('cascade');

            $table->index(['store_requisition_id', 'store_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_requisition_items');
    }
};
