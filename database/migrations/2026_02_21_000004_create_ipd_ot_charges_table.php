<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipd_ot_charges', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ipd_patient_id');
            $table->foreign('ipd_patient_id')->references('id')->on('ipdpatients')->onDelete('cascade');

            // Optional reference to master charges table
            $table->unsignedBigInteger('charge_id')->nullable();
            $table->foreign('charge_id')->references('id')->on('charges')->nullOnDelete();

            $table->string('charge_name')->nullable();
            $table->string('procedure_name')->nullable();

            $table->dateTime('performed_at')->nullable();

            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ipd_patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipd_ot_charges');
    }
};
