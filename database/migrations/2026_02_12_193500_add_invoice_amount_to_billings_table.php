<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('billings', function (Blueprint $table) {
            if (!Schema::hasColumn('billings', 'invoice_amount')) {
                $table->decimal('invoice_amount', 10, 2)->default(0)->after('paid_amt');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('billings', function (Blueprint $table) {
            if (Schema::hasColumn('billings', 'invoice_amount')) {
                $table->dropColumn('invoice_amount');
            }
        });
    }
};
