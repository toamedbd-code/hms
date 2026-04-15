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
        Schema::table('medicineinventories', function (Blueprint $table) {
            if (!Schema::hasColumn('medicineinventories', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('medicine_quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicineinventories', function (Blueprint $table) {
            if (Schema::hasColumn('medicineinventories', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }
        });
    }
};
