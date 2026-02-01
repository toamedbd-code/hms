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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            
            // Billing reference
            $table->unsignedBigInteger('billing_id');
            $table->foreign('billing_id')->references('id')->on('billings')->onDelete('cascade');
            
            // Payee (Referrer) reference
            $table->unsignedBigInteger('payee_id');
            $table->foreign('payee_id')->references('id')->on('referralpeople')->onDelete('cascade');
            
            // Commission details
            $table->decimal('total_commission_amount', 10, 2)->default(0);
            $table->json('category_commissions')->nullable(); // Store category-wise commission breakdown
            
            $table->date('date');
            $table->decimal('total_bill_amount', 10, 2)->default(0);

            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->text('remarks')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index(['billing_id', 'payee_id']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referrals');
    }
};