<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opd_prescription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opd_prescription_id');
            $table->string('medicine_name');
            $table->string('dose');
            $table->string('duration');
            $table->string('frequency')->nullable();
            $table->string('instructions')->nullable();
            $table->timestamps();

            $table->foreign('opd_prescription_id')
                ->references('id')
                ->on('opd_prescriptions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opd_prescription_items');
    }
};
