<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referralpeople', function (Blueprint $table) {
            $table->json('category_commissions')->nullable()->after('ambulance_commission');
        });
    }

    public function down(): void
    {
        Schema::table('referralpeople', function (Blueprint $table) {
            $table->dropColumn('category_commissions');
        });
    }
};
