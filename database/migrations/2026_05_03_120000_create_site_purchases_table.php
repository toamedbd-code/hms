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
        Schema::create('site_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 64)->unique();
            $table->string('site_name', 150);
            $table->string('vendor_name', 150)->nullable();
            $table->string('item_name', 200);
            $table->string('category_name', 120)->nullable();
            $table->enum('purchase_nature', ['asset', 'expense'])->default('expense');
            $table->date('purchase_date');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('due_amount', 14, 2)->default(0);
            $table->enum('payment_status', ['paid', 'partial', 'pending'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_purchases');
    }
};
