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
        if (Schema::hasTable('bill_items')) {
            Schema::table('bill_items', function (Blueprint $table) {
                // allow ad-hoc/manual items without a referenced item id
                $table->unsignedBigInteger('item_id')->nullable()->change();
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
                $table->unsignedBigInteger('item_id')->nullable(false)->change();
            });
        }
    }
};
