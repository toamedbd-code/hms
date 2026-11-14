<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `invoicedesigns` MODIFY `module` ENUM('opd','ipd','ipd_final','pathology','radiology','pharmacy','appointment','billing','prescription') NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE `invoicedesigns` SET `module` = 'ipd' WHERE `module` = 'ipd_final'");
        DB::statement("ALTER TABLE `invoicedesigns` MODIFY `module` ENUM('opd','ipd','pathology','radiology','pharmacy','appointment','billing','prescription') NULL");
    }
};
