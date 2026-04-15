<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'attendance_device_enabled')) {
                $table->boolean('attendance_device_enabled')->default(false)->after('report_title');
            }

            if (!Schema::hasColumn('web_settings', 'attendance_device_type')) {
                $table->string('attendance_device_type')->nullable()->after('attendance_device_enabled');
            }

            if (!Schema::hasColumn('web_settings', 'attendance_device_identifier')) {
                $table->string('attendance_device_identifier')->nullable()->after('attendance_device_type');
            }

            if (!Schema::hasColumn('web_settings', 'attendance_device_ip')) {
                $table->string('attendance_device_ip')->nullable()->after('attendance_device_identifier');
            }

            if (!Schema::hasColumn('web_settings', 'attendance_device_port')) {
                $table->string('attendance_device_port')->nullable()->after('attendance_device_ip');
            }

            if (!Schema::hasColumn('web_settings', 'attendance_device_secret')) {
                $table->string('attendance_device_secret')->nullable()->after('attendance_device_port');
            }

            if (!Schema::hasColumn('web_settings', 'attendance_device_options')) {
                $table->text('attendance_device_options')->nullable()->after('attendance_device_secret');
            }
        });
    }

    public function down()
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'attendance_device_options')) {
                $table->dropColumn('attendance_device_options');
            }
            if (Schema::hasColumn('web_settings', 'attendance_device_secret')) {
                $table->dropColumn('attendance_device_secret');
            }
            if (Schema::hasColumn('web_settings', 'attendance_device_port')) {
                $table->dropColumn('attendance_device_port');
            }
            if (Schema::hasColumn('web_settings', 'attendance_device_ip')) {
                $table->dropColumn('attendance_device_ip');
            }
            if (Schema::hasColumn('web_settings', 'attendance_device_identifier')) {
                $table->dropColumn('attendance_device_identifier');
            }
            if (Schema::hasColumn('web_settings', 'attendance_device_type')) {
                $table->dropColumn('attendance_device_type');
            }
            if (Schema::hasColumn('web_settings', 'attendance_device_enabled')) {
                $table->dropColumn('attendance_device_enabled');
            }
        });
    }
};
