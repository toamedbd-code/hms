<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bill_item_parameter_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bill_item_id')->index();
            $table->unsignedBigInteger('pathology_test_parameter_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('value')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->foreign('bill_item_id')->references('id')->on('bill_items')->onDelete('cascade');
            $table->foreign('pathology_test_parameter_id')->references('id')->on('pathology_test_parameters')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bill_item_parameter_results');
    }
};
