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
            if (!Schema::hasColumn('web_settings', 'hospital_code')) {
                $table->string('hospital_code', 100)->nullable()->after('company_short_name');
            }
            if (!Schema::hasColumn('web_settings', 'address')) {
                $table->string('address', 500)->nullable()->after('hospital_code');
            }
            if (!Schema::hasColumn('web_settings', 'email')) {
                $table->string('email', 255)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('web_settings', 'language')) {
                $table->string('language', 50)->default('English')->after('icon');
            }
            if (!Schema::hasColumn('web_settings', 'date_format')) {
                $table->string('date_format', 50)->default('dd-mm-yyyy')->after('language');
            }
            if (!Schema::hasColumn('web_settings', 'time_zone')) {
                $table->string('time_zone', 100)->default('(GMT+06:00) Asia, Dhaka')->after('date_format');
            }
            if (!Schema::hasColumn('web_settings', 'currency')) {
                $table->string('currency', 20)->default('BDT')->after('time_zone');
            }
            if (!Schema::hasColumn('web_settings', 'currency_symbol')) {
                $table->string('currency_symbol', 20)->default('Tk.')->after('currency');
            }
            if (!Schema::hasColumn('web_settings', 'credit_limit')) {
                $table->decimal('credit_limit', 12, 2)->default(10000)->after('currency_symbol');
            }
            if (!Schema::hasColumn('web_settings', 'time_format')) {
                $table->string('time_format', 20)->default('12 Hour')->after('credit_limit');
            }
            if (!Schema::hasColumn('web_settings', 'mobile_app_api_url')) {
                $table->string('mobile_app_api_url', 255)->nullable()->after('time_format');
            }
            if (!Schema::hasColumn('web_settings', 'mobile_app_primary_color_code')) {
                $table->string('mobile_app_primary_color_code', 20)->default('444444')->after('mobile_app_api_url');
            }
            if (!Schema::hasColumn('web_settings', 'mobile_app_secondary_color_code')) {
                $table->string('mobile_app_secondary_color_code', 20)->default('ffffff')->after('mobile_app_primary_color_code');
            }
            if (!Schema::hasColumn('web_settings', 'mobile_app_logo')) {
                $table->string('mobile_app_logo')->nullable()->after('mobile_app_secondary_color_code');
            }
            if (!Schema::hasColumn('web_settings', 'doctor_restriction_mode')) {
                $table->boolean('doctor_restriction_mode')->default(false)->after('mobile_app_logo');
            }
            if (!Schema::hasColumn('web_settings', 'superadmin_visibility')) {
                $table->boolean('superadmin_visibility')->default(false)->after('doctor_restriction_mode');
            }
            if (!Schema::hasColumn('web_settings', 'patient_panel')) {
                $table->boolean('patient_panel')->default(false)->after('superadmin_visibility');
            }
            if (!Schema::hasColumn('web_settings', 'scan_type')) {
                $table->string('scan_type', 20)->default('Barcode')->after('patient_panel');
            }
            if (!Schema::hasColumn('web_settings', 'current_theme')) {
                $table->string('current_theme', 20)->default('default')->after('scan_type');
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
                'current_theme',
                'scan_type',
                'patient_panel',
                'superadmin_visibility',
                'doctor_restriction_mode',
                'mobile_app_logo',
                'mobile_app_secondary_color_code',
                'mobile_app_primary_color_code',
                'mobile_app_api_url',
                'time_format',
                'credit_limit',
                'currency_symbol',
                'currency',
                'time_zone',
                'date_format',
                'language',
                'email',
                'address',
                'hospital_code',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('web_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
