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
        Schema::create('refund_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id');
            $table->string('bill_number')->nullable();
            $table->decimal('refund_amount', 10, 2);
            $table->string('payment_method')->default('cash');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('billing_id')->references('id')->on('billings')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('admins')->onDelete('set null');
            $table->index(['billing_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_transactions');
    }
};
