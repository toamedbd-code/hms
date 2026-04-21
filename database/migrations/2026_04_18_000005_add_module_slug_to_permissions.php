<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'module_slug')) {
                $table->string('module_slug')->nullable()->after('name');
            }
        });
    }

    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'module_slug')) {
                $table->dropColumn('module_slug');
            }
        });
    }
};
