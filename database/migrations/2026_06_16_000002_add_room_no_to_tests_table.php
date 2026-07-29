<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('tests', 'room_no')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->string('room_no')->nullable()->after('report_days');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tests', 'room_no')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->dropColumn('room_no');
            });
        }
    }
};
