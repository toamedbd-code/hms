<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'vat_enabled')) {
                $table->boolean('vat_enabled')->default(false)->after('currency_symbol');
            }
            if (!Schema::hasColumn('web_settings', 'vat_percent')) {
                $table->decimal('vat_percent', 5, 2)->default(0.00)->after('vat_enabled');
            }
        });
    }

    public function down()
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'vat_percent')) {
                $table->dropColumn('vat_percent');
            }
            if (Schema::hasColumn('web_settings', 'vat_enabled')) {
                $table->dropColumn('vat_enabled');
            }
        });
    }
};
