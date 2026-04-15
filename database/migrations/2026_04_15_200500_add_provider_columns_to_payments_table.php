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

        if (! Schema::hasColumn('payments', 'provider')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('provider')->default('bkash')->after('id');
            });
        }

        if (! Schema::hasColumn('payments', 'provider_payment_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('provider_payment_id')->nullable()->after('provider');
            });
        }

        if (! Schema::hasColumn('payments', 'metadata')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('status');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (Schema::hasColumn('payments', 'metadata')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }

        if (Schema::hasColumn('payments', 'provider_payment_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('provider_payment_id');
            });
        }

        if (Schema::hasColumn('payments', 'provider')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('provider');
            });
        }
    }
};
