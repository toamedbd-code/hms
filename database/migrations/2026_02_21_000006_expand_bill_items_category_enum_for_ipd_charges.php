<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: bill_items.category was created as ENUM in an old migration.
        // We expand it for IPD discharge billing so bed/room rent/OT/doctor visit can be stored.
        DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','Medicine','Room Rent','Bed Charge','OT','Doctor Visit') NOT NULL");
    }

    public function down(): void
    {
        // Rollback to original values.
        DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','Medicine') NOT NULL");
    }
};
