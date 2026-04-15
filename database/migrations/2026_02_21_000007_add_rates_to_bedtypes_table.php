<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bedtypes', function (Blueprint $table) {
            $table->decimal('room_rent_rate_per_day', 10, 2)
                ->default(0)
                ->after('name');

            $table->decimal('bed_charge_rate_per_day', 10, 2)
                ->default(0)
                ->after('room_rent_rate_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('bedtypes', function (Blueprint $table) {
            $table->dropColumn(['room_rent_rate_per_day', 'bed_charge_rate_per_day']);
        });
    }
};
