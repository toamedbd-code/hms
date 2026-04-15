<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipd_prescription_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ipd_prescription_id');
            $table->string('test_name');
            $table->timestamps();

            $table->foreign('ipd_prescription_id')
                ->references('id')
                ->on('ipd_prescriptions')
                ->onDelete('cascade');

            $table->index(['ipd_prescription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipd_prescription_tests');
    }
};
