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
        Schema::create('radiologies', function (Blueprint $table) {
            $table->id();

            $table->string('case_id')->unique();
            $table->string('bill_no')->unique();
            $table->string('radiology_no')->unique();

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->on('patients')->references('id')->onDelete('cascade');

            $table->unsignedBigInteger('referral_doctor_id')->nullable();
            // $table->foreign('referral_doctor_id')->on('admins')->references('id')->onDelete('cascade');

            $table->string('doctor_name')->nullable();
            $table->text('note')->nullable();

            $table->json('test_details')->nullable();

            // Financial fields
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);

            // Payment fields
            $table->string('payment_mode');
            $table->decimal('payment_amount', 10, 2)->default(0);

            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->on('admins')->references('id')->onDelete('cascade');

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->on('admins')->references('id')->onDelete('cascade');

            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            // // Foreign keys
            // $table->foreign('patient_id')->references('id')->on('patients');
            // $table->foreign('referral_doctor')->references('id')->on('doctors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('radiologies');
    }
};
