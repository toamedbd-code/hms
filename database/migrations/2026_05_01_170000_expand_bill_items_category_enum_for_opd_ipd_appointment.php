<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bill_items')) {
            DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','Medicine','Room Rent','Bed Charge','OT','Doctor Visit','OPD','IPD','Appointment') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bill_items')) {
            DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','Medicine','Room Rent','Bed Charge','OT','Doctor Visit') NOT NULL");
        }
    }
};
