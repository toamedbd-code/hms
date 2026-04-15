<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('face_encodings')) return;

        Schema::create('face_encodings', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->index();
            $table->json('descriptor');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('face_encodings');
    }
};
