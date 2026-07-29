<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
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
        if (Schema::hasTable('bill_items')) {
            // Add ECG/Ultrasound/Ultrasonogram to ENUM values
            DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','ECG','Ultrasound','Ultrasonogram','Medicine','Room Rent','Bed Charge','OT','Doctor Visit','OPD','IPD','Appointment') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('bill_items')) {
            // Revert to previous set (without ECG/Ultrasound)
            DB::statement("ALTER TABLE `bill_items` MODIFY `category` ENUM('Pathology','Radiology','Medicine','Room Rent','Bed Charge','OT','Doctor Visit','OPD','IPD','Appointment') NOT NULL");
        }
    }
};
