<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billings')->onDelete('cascade');

            // Item Details
            $table->unsignedBigInteger('item_id'); // ID from medicine_inventories or pathology_radiology_tests
            $table->string('item_name');
            $table->enum('category', ['Pathology', 'Radiology', 'Medicine']);

            // Pricing
            $table->decimal('unit_price', 10, 2);
            $table->decimal('quantity', 10, 3); // Allow decimal quantities like 1.50
            $table->decimal('total_amount', 10, 2);

            // Adjustments
            $table->decimal('discount', 10, 2)->default(0); // Can be percentage or flat amount
            $table->decimal('rugound', 10, 2)->default(0);  // Rounding adjustment
            $table->decimal('net_amount', 10, 2);

            // System Fields
            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bill_items');
    }
};
