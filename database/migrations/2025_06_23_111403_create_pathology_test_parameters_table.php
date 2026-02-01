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
        Schema::create('pathology_test_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pathology_test_id');
            $table->unsignedBigInteger('test_parameter_id')->nullable();
            $table->string('name')->nullable();
            $table->string('reference_from')->nullable();
            $table->string('reference_to')->nullable();
            $table->unsignedBigInteger('pathology_unit_id')->nullable();
            
            $table->timestamps();
            
            // $table->foreign('pathology_test_id')->references('id')->on('pathologytests')->onDelete('cascade');
            // $table->foreign('test_parameter_id')->references('id')->on('test_parameters')->onDelete('set null');
            // $table->foreign('pathology_unit_id')->references('id')->on('pathology_units')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pathology_test_parameters');
    }
};
