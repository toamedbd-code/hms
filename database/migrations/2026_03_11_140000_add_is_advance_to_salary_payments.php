<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('salary_payments')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('salary_payments', 'is_advance')) {
                    $table->boolean('is_advance')->default(false)->after('note');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('salary_payments')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                if (Schema::hasColumn('salary_payments', 'is_advance')) {
                    $table->dropColumn('is_advance');
                }
            });
        }
    }
};
