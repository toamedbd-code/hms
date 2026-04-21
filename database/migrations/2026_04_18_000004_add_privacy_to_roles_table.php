<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'is_private')) {
                $table->boolean('is_private')->default(false)->after('guard_name');
            }

            if (!Schema::hasColumn('roles', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('is_private');
                $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('roles', 'is_private')) {
                $table->dropColumn('is_private');
            }
        });
    }
};
