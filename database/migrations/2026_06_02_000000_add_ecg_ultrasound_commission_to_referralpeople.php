<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('referralpeople', function (Blueprint $table) {
            if (!Schema::hasColumn('referralpeople', 'ecg_commission')) {
                $table->decimal('ecg_commission', 5, 2)->nullable()->default(0.00)->after('radiology_commission');
            }
            if (!Schema::hasColumn('referralpeople', 'ultrasound_commission')) {
                $table->decimal('ultrasound_commission', 5, 2)->nullable()->default(0.00)->after('ecg_commission');
            }
        });
    }

    public function down()
    {
        Schema::table('referralpeople', function (Blueprint $table) {
            if (Schema::hasColumn('referralpeople', 'ultrasound_commission')) {
                $table->dropColumn('ultrasound_commission');
            }
            if (Schema::hasColumn('referralpeople', 'ecg_commission')) {
                $table->dropColumn('ecg_commission');
            }
        });
    }
};
