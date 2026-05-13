<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SyncReportSummaryMenu extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ensure any existing Doctor/Report Summary menu rows point to the
        // new named route and use a permission likely available to report
        // users so the item becomes visible in the sidebar.
        DB::table('menus')
            ->where('name', 'Report Summary')
            ->orWhere('name', 'Doctor Summary')
            ->orWhere('route', 'backend.doctor-summary.index')
            ->update([
                'route' => 'backend.report-summary.index',
                // Use report-list so users who can view reports will see it
                'permission_name' => 'report-list',
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('menus')
            ->where('route', 'backend.report-summary.index')
            ->update([
                'route' => 'backend.doctor-summary.index',
                'permission_name' => 'report-management',
            ]);
    }
}
