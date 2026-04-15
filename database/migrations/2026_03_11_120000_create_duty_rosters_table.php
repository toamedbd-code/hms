<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('duty_rosters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('shift_name')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['Active','Inactive'])->default('Active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id','date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('duty_rosters');
    }
};
