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
        Schema::create('define_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
			$table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
			$table->unsignedBigInteger('type_id');
			$table->foreign('type_id')->references('id')->on('leave_types')->onDelete('cascade');
			$table->integer('days');
			
            $table->enum('status',['Active','Inactive','Deleted'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
         
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('define_leaves');
    }
};
