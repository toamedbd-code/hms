<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommissionSnapshotToBillItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('bill_items')) {
            Schema::table('bill_items', function (Blueprint $table) {
                if (!Schema::hasColumn('bill_items', 'commissionable')) {
                    $table->boolean('commissionable')->nullable()->after('net_amount');
                }
                if (!Schema::hasColumn('bill_items', 'commission_rate')) {
                    $table->decimal('commission_rate', 5, 2)->nullable()->after('commissionable');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('bill_items')) {
            Schema::table('bill_items', function (Blueprint $table) {
                if (Schema::hasColumn('bill_items', 'commission_rate')) {
                    $table->dropColumn('commission_rate');
                }
                if (Schema::hasColumn('bill_items', 'commissionable')) {
                    $table->dropColumn('commissionable');
                }
            });
        }
    }
}
