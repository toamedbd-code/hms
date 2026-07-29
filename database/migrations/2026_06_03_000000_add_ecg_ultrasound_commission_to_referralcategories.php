<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('referralcategories', function (Blueprint $table) {
            if (!Schema::hasColumn('referralcategories', 'ecg_commission')) {
                $table->decimal('ecg_commission', 5, 2)->nullable()->default(0.00);
            }
            if (!Schema::hasColumn('referralcategories', 'ultrasound_commission')) {
                $table->decimal('ultrasound_commission', 5, 2)->nullable()->default(0.00);
            }
        });
    }

    public function down()
    {
        Schema::table('referralcategories', function (Blueprint $table) {
            if (Schema::hasColumn('referralcategories', 'ultrasound_commission')) {
                $table->dropColumn('ultrasound_commission');
            }
            if (Schema::hasColumn('referralcategories', 'ecg_commission')) {
                $table->dropColumn('ecg_commission');
            }
        });
    }
};
