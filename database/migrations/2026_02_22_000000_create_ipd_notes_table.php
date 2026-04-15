<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ipd_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ipd_patient_id');
            $table->string('type'); // nurse_note, consultant_register, operation, bed_history
            $table->text('content')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();

            $table->foreign('ipd_patient_id')->references('id')->on('ipdpatients')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ipd_notes');
    }
};
