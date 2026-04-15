<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('payments')) {
            // Use raw statement to avoid requiring doctrine/dbal for change()
            DB::statement("ALTER TABLE `payments` CHANGE `status` `status` VARCHAR(255) NOT NULL DEFAULT 'initiated';");
        }
    }

    public function down()
    {
        if (Schema::hasTable('payments')) {
            // Try to revert: set unknown values to 'Active' then change to enum
            DB::statement("UPDATE `payments` SET `status` = 'Active' WHERE `status` NOT IN ('Active','Inactive','Deleted');");
            DB::statement("ALTER TABLE `payments` CHANGE `status` `status` ENUM('Active','Inactive','Deleted') NOT NULL DEFAULT 'Active';");
        }
    }
};
