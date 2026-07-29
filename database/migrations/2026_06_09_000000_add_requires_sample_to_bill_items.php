<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('bill_items')) {
            Schema::table('bill_items', function (Blueprint $table) {
                if (! Schema::hasColumn('bill_items', 'requires_sample')) {
                    $table->boolean('requires_sample')->default(true)->after('category');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('bill_items')) {
            Schema::table('bill_items', function (Blueprint $table) {
                if (Schema::hasColumn('bill_items', 'requires_sample')) {
                    $table->dropColumn('requires_sample');
                }
            });
        }
    }
};
