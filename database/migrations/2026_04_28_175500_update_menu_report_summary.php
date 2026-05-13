<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateMenuReportSummary extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('menus')
            ->where('route', 'backend.doctor-summary.index')
            ->update(['name' => 'Report Summary']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('menus')
            ->where('route', 'backend.doctor-summary.index')
            ->update(['name' => 'Doctor Summary']);
    }
}
