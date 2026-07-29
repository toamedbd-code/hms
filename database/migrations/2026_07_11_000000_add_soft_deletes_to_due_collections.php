<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('due_collections')) {
            Schema::table('due_collections', function (Blueprint $table) {
                if (!Schema::hasColumn('due_collections', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('due_collections')) {
            Schema::table('due_collections', function (Blueprint $table) {
                if (Schema::hasColumn('due_collections', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
