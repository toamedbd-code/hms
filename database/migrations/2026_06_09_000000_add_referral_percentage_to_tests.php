<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('tests', 'referral_percentage')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->decimal('referral_percentage', 5, 2)->nullable()->default(0.00)->after('commission_rate');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('tests', 'referral_percentage')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->dropColumn('referral_percentage');
            });
        }
    }
};
