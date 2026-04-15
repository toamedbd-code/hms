<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bedtypes', function (Blueprint $table) {
            if (!Schema::hasColumn('bedtypes', 'room_rent_rate_per_day')) {
                $table->decimal('room_rent_rate_per_day', 10, 2)->default(0)->after('name');
            }
            if (!Schema::hasColumn('bedtypes', 'bed_charge_rate_per_day')) {
                $table->decimal('bed_charge_rate_per_day', 10, 2)->default(0)->after('room_rent_rate_per_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bedtypes', function (Blueprint $table) {
            if (Schema::hasColumn('bedtypes', 'room_rent_rate_per_day')) {
                $table->dropColumn('room_rent_rate_per_day');
            }
            if (Schema::hasColumn('bedtypes', 'bed_charge_rate_per_day')) {
                $table->dropColumn('bed_charge_rate_per_day');
            }
        });
    }
};
