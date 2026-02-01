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
        Schema::create('radiology_tests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radiology_id');
            $table->string('test_id');
            $table->integer('report_days')->nullable();
            $table->date('report_date')->nullable();
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);

            // $table->foreign('radiology_id')->references('id')->on('radiologies')->onDelete('cascade');
            // $table->foreign('test_id')->references('id')->on('tests');
        
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
        Schema::dropIfExists('radiology_tests');
    }
};
