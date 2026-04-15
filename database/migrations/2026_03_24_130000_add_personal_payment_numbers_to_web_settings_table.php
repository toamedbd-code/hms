<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'personal_bkash_number')) {
                $table->string('personal_bkash_number', 30)->nullable()->after('sms_additional_params');
            }

            if (!Schema::hasColumn('web_settings', 'personal_nagad_number')) {
                $table->string('personal_nagad_number', 30)->nullable()->after('personal_bkash_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'personal_nagad_number')) {
                $table->dropColumn('personal_nagad_number');
            }

            if (Schema::hasColumn('web_settings', 'personal_bkash_number')) {
                $table->dropColumn('personal_bkash_number');
            }
        });
    }
};
