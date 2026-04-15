<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->default(0)->after('total_commission_amount');
            $table->enum('paid_status', ['Unpaid', 'Partial Paid', 'Paid'])->default('Unpaid')->after('paid_amount');
            $table->dateTime('last_paid_at')->nullable()->after('paid_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'paid_status', 'last_paid_at']);
        });
    }
};
