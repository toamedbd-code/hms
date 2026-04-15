<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'ipd_no_prefix')) {
                $table->string('ipd_no_prefix', 20)->default('IPDN')->after('current_theme');
            }
            if (!Schema::hasColumn('web_settings', 'opd_no_prefix')) {
                $table->string('opd_no_prefix', 20)->default('OPDN')->after('ipd_no_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'ipd_prescription_prefix')) {
                $table->string('ipd_prescription_prefix', 20)->default('IPDP')->after('opd_no_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'opd_prescription_prefix')) {
                $table->string('opd_prescription_prefix', 20)->default('OPDP')->after('ipd_prescription_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'appointment_prefix')) {
                $table->string('appointment_prefix', 20)->default('APPN')->after('opd_prescription_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'pharmacy_bill_prefix')) {
                $table->string('pharmacy_bill_prefix', 20)->default('PHAB')->after('appointment_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'billing_bill_prefix')) {
                $table->string('billing_bill_prefix', 20)->default('BILL')->after('pharmacy_bill_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'operation_reference_no_prefix')) {
                $table->string('operation_reference_no_prefix', 20)->default('OTRN')->after('billing_bill_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'blood_bank_bill_prefix')) {
                $table->string('blood_bank_bill_prefix', 20)->default('BLBB')->after('operation_reference_no_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'ambulance_call_bill_prefix')) {
                $table->string('ambulance_call_bill_prefix', 20)->default('AMCB')->after('blood_bank_bill_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'radiology_bill_prefix')) {
                $table->string('radiology_bill_prefix', 20)->default('RADB')->after('ambulance_call_bill_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'pathology_bill_prefix')) {
                $table->string('pathology_bill_prefix', 20)->default('Bill')->after('radiology_bill_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'opd_checkup_id_prefix')) {
                $table->string('opd_checkup_id_prefix', 20)->default('OCID')->after('pathology_bill_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'pharmacy_purchase_no_prefix')) {
                $table->string('pharmacy_purchase_no_prefix', 20)->default('PHPN')->after('opd_checkup_id_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'transaction_id_prefix')) {
                $table->string('transaction_id_prefix', 20)->default('TRID')->after('pharmacy_purchase_no_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'birth_record_reference_no_prefix')) {
                $table->string('birth_record_reference_no_prefix', 20)->default('BRRN')->after('transaction_id_prefix');
            }
            if (!Schema::hasColumn('web_settings', 'death_record_reference_no_prefix')) {
                $table->string('death_record_reference_no_prefix', 20)->default('DRRN')->after('birth_record_reference_no_prefix');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $columns = [
                'death_record_reference_no_prefix',
                'birth_record_reference_no_prefix',
                'transaction_id_prefix',
                'pharmacy_purchase_no_prefix',
                'opd_checkup_id_prefix',
                'pathology_bill_prefix',
                'radiology_bill_prefix',
                'ambulance_call_bill_prefix',
                'blood_bank_bill_prefix',
                'operation_reference_no_prefix',
                'billing_bill_prefix',
                'pharmacy_bill_prefix',
                'appointment_prefix',
                'opd_prescription_prefix',
                'ipd_prescription_prefix',
                'opd_no_prefix',
                'ipd_no_prefix',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('web_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
