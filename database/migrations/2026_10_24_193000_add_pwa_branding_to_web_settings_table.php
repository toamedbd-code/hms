<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'pwa_app_name')) {
                $table->string('pwa_app_name', 255)->nullable();
            }

            if (!Schema::hasColumn('web_settings', 'pwa_app_icon')) {
                $table->string('pwa_app_icon')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('web_settings', 'pwa_app_icon') ? 'pwa_app_icon' : null,
                Schema::hasColumn('web_settings', 'pwa_app_name') ? 'pwa_app_name' : null,
            ]);

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
