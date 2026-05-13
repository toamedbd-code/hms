<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommissionFieldsToTestsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add to generic tests table if present
        if (Schema::hasTable('tests')) {
            Schema::table('tests', function (Blueprint $table) {
                if (!Schema::hasColumn('tests', 'commissionable')) {
                    $table->boolean('commissionable')->nullable()->after('status');
                }
                if (!Schema::hasColumn('tests', 'commission_rate')) {
                    $table->decimal('commission_rate', 5, 2)->nullable()->after('commissionable');
                }
            });
        }

        if (Schema::hasTable('pathologytests')) {
            Schema::table('pathologytests', function (Blueprint $table) {
                if (!Schema::hasColumn('pathologytests', 'commissionable')) {
                    $table->boolean('commissionable')->nullable()->after('status');
                }
                if (!Schema::hasColumn('pathologytests', 'commission_rate')) {
                    $table->decimal('commission_rate', 5, 2)->nullable()->after('commissionable');
                }
            });
        }

        if (Schema::hasTable('radiologytests')) {
            Schema::table('radiologytests', function (Blueprint $table) {
                if (!Schema::hasColumn('radiologytests', 'commissionable')) {
                    $table->boolean('commissionable')->nullable()->after('status');
                }
                if (!Schema::hasColumn('radiologytests', 'commission_rate')) {
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
        if (Schema::hasTable('tests')) {
            Schema::table('tests', function (Blueprint $table) {
                if (Schema::hasColumn('tests', 'commission_rate')) {
                    $table->dropColumn('commission_rate');
                }
                if (Schema::hasColumn('tests', 'commissionable')) {
                    $table->dropColumn('commissionable');
                }
            });
        }

        if (Schema::hasTable('pathologytests')) {
            Schema::table('pathologytests', function (Blueprint $table) {
                if (Schema::hasColumn('pathologytests', 'commission_rate')) {
                    $table->dropColumn('commission_rate');
                }
                if (Schema::hasColumn('pathologytests', 'commissionable')) {
                    $table->dropColumn('commissionable');
                }
            });
        }

        if (Schema::hasTable('radiologytests')) {
            Schema::table('radiologytests', function (Blueprint $table) {
                if (Schema::hasColumn('radiologytests', 'commission_rate')) {
                    $table->dropColumn('commission_rate');
                }
                if (Schema::hasColumn('radiologytests', 'commissionable')) {
                    $table->dropColumn('commissionable');
                }
            });
        }
    }
}
