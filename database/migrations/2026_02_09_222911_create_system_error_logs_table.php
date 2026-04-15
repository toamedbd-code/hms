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
        Schema::create('system_error_logs', function (Blueprint $table) {
    $table->id();
    $table->string('namespace')->nullable();
    $table->string('controller')->nullable();
    $table->string('function')->nullable();
    $table->text('log')->nullable();
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
        Schema::dropIfExists('system_error_logs');
    }
};