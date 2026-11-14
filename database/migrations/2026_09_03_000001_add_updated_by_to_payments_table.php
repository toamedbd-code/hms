<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'updated_by')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('received_by');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'updated_by')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('updated_by');
            });
        }
    }
};
