<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('identifier')->unique(); // serial, ip, or custom id
            $table->string('type')->default('fingerprint'); // fingerprint|face
            $table->string('secret')->nullable(); // shared secret for webhook auth
            $table->enum('status', ['Active','Inactive'])->default('Active');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_devices');
    }
};
