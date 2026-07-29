<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('charges', 'module')) {
            Schema::table('charges', function (Blueprint $table) {
                $table->text('module')->nullable()->after('description');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('charges', 'module')) {
            Schema::table('charges', function (Blueprint $table) {
                $table->dropColumn('module');
            });
        }
    }
};
