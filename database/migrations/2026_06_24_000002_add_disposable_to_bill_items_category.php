<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('bill_items')) {
            // Add 'Disposable' to allowed categories for bill_items.category
            DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','ECG','Ultrasound','Ultrasonogram','Medicine','Room Rent','Bed Charge','OT','Doctor Visit','OPD','IPD','Appointment','Disposable') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bill_items')) {
            // Revert to previous enum set (without Disposable)
            DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','ECG','Ultrasound','Ultrasonogram','Medicine','Room Rent','Bed Charge','OT','Doctor Visit','OPD','IPD','Appointment') NOT NULL");
        }
    }
};
