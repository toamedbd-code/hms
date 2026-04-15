<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up()
    {
        // Make received_by nullable so public-initiated payments can be created.
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'received_by')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('received_by')->nullable();
            });
        } else {
            DB::statement("ALTER TABLE `payments` MODIFY `received_by` BIGINT UNSIGNED NULL;");
        }
    }

    public function down()
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'received_by')) {
            return;
        }

        // Fill nulls with 0 and revert to NOT NULL with a default to avoid failure for older code.
        DB::statement("UPDATE `payments` SET `received_by` = 0 WHERE `received_by` IS NULL;");
        DB::statement("ALTER TABLE `payments` MODIFY `received_by` BIGINT UNSIGNED NOT NULL DEFAULT 0;");
    }
};
