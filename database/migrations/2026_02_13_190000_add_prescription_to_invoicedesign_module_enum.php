<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `invoicedesigns` MODIFY `module` ENUM('opd','ipd','pathology','radiology','pharmacy','appointment','billing','prescription') NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("UPDATE `invoicedesigns` SET `module` = 'opd' WHERE `module` = 'prescription'");
        DB::statement("ALTER TABLE `invoicedesigns` MODIFY `module` ENUM('opd','ipd','pathology','radiology','pharmacy','appointment','billing') NULL");
    }
};
