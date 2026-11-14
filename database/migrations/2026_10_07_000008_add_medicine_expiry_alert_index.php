<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicineinventories', function (Blueprint $table) {
            $table->index(['status', 'expiry_date'], 'medinv_status_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('medicineinventories', function (Blueprint $table) {
            $table->dropIndex('medinv_status_expiry_idx');
        });
    }
};
