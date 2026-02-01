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
        Schema::create('apply_leaves', function (Blueprint $table) {
            $table->id();
            $table->date( 'apply_date');
			$table->unsignedBigInteger( 'leave_type_id');
			$table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
			
			$table->unsignedBigInteger( 'employee_id');
			$table->foreign('employee_id')->references('id')->on('admins')->onDelete('cascade');
			$table->date('from');
			$table->date('to');
			$table->string('reason', 255);
			$table->string('attachment', 255);

            $table->enum('status',['Active', 'Pending', 'Approved','Rejected'])->default('Pending');
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
        Schema::dropIfExists('apply_leaves');
    }
};
