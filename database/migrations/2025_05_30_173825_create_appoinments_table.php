<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appoinments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->on('patients')->references('id')->onDelete('cascade');
            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->on('admins')->references('id')->onDelete('cascade');

            $table->decimal('doctor_fee', 10, 2)->nullable();
            $table->enum('shift', ['Morning', 'Evening', 'Night'])->nullable();
            $table->datetime('appoinment_date');
            $table->string('slot')->nullable();
            $table->enum('appointment_priority', ['Normal', 'Urgent', 'Very Urgent', 'Low'])->default('Normal');

            $table->enum('payment_mode', ['Cash', 'Cheque', 'Transfer to Bank Account', 'Upi', 'Online', 'Other'])->nullable();
            $table->string('transaction_id')->unique();
            $table->decimal('discount_percentage', 5, 2)->nullable();

            $table->enum('appoinment_status', ['Pending', 'Approved', 'Cancelled', 'Completed'])->default('Pending');
            $table->enum('live_consultant', ['Yes', 'No'])->default('No');
            $table->text('message')->nullable();

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
        Schema::dropIfExists('appoinments');
    }
};
