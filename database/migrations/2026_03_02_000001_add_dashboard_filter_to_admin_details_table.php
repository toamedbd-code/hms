<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('admin_details', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_details', 'dashboard_filter_type')) {
                $table->string('dashboard_filter_type')->nullable()->after('status');
            }
            if (!Schema::hasColumn('admin_details', 'dashboard_filter_from')) {
                $table->date('dashboard_filter_from')->nullable()->after('dashboard_filter_type');
            }
            if (!Schema::hasColumn('admin_details', 'dashboard_filter_to')) {
                $table->date('dashboard_filter_to')->nullable()->after('dashboard_filter_from');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin_details', function (Blueprint $table) {
            if (Schema::hasColumn('admin_details', 'dashboard_filter_to')) {
                $table->dropColumn('dashboard_filter_to');
            }
            if (Schema::hasColumn('admin_details', 'dashboard_filter_from')) {
                $table->dropColumn('dashboard_filter_from');
            }
            if (Schema::hasColumn('admin_details', 'dashboard_filter_type')) {
                $table->dropColumn('dashboard_filter_type');
            }
        });
    }
};
