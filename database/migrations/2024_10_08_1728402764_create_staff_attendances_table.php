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
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->string('staff_id', 255);
			$table->string('name', 255);
			$table->enum('attendance_status', ['Present','Late','Absent','Holiday'])->nullable();
			$table->time('in_time')->nullable();
			$table->time('out_time')->nullable();
			$table->date('attendance_date');
			$table->string('note', 255)->nullable();
			
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
        Schema::dropIfExists('staff_attendances');
    }
};
