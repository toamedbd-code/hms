<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipd_prescription_medicines', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('generic_name');
        });

        Schema::table('pharmacybills', function (Blueprint $table) {
            $table->unsignedBigInteger('ipd_patient_id')->nullable()->after('patient_id');
            $table->unsignedBigInteger('ipd_prescription_id')->nullable()->after('ipd_patient_id');
            $table->unsignedBigInteger('billing_id')->nullable()->after('ipd_prescription_id');

            $table->foreign('ipd_patient_id')->references('id')->on('ipdpatients')->nullOnDelete();
            $table->foreign('ipd_prescription_id')->references('id')->on('ipd_prescriptions')->nullOnDelete();
            $table->foreign('billing_id')->references('id')->on('billings')->nullOnDelete();
            $table->index(['patient_id', 'ipd_patient_id']);
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->unsignedBigInteger('pharmacy_bill_id')->nullable()->after('billing_id');
            $table->foreign('pharmacy_bill_id')->references('id')->on('pharmacybills')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropForeign(['pharmacy_bill_id']);
            $table->dropColumn('pharmacy_bill_id');
        });

        Schema::table('pharmacybills', function (Blueprint $table) {
            $table->dropForeign(['ipd_patient_id']);
            $table->dropForeign(['ipd_prescription_id']);
            $table->dropForeign(['billing_id']);
            $table->dropIndex(['patient_id', 'ipd_patient_id']);
            $table->dropColumn(['ipd_patient_id', 'ipd_prescription_id', 'billing_id']);
        });

        Schema::table('ipd_prescription_medicines', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
