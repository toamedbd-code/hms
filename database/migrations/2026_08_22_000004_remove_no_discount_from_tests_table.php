<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tests', 'no_discount') && Schema::hasColumn('tests', 'discount_percentage')) {
            DB::table('tests')->where('no_discount', true)->update(['discount_percentage' => 0]);
        }

        Schema::table('tests', function (Blueprint $table) {
            if (Schema::hasColumn('tests', 'no_discount')) {
                $table->dropColumn('no_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            if (!Schema::hasColumn('tests', 'no_discount')) {
                $table->boolean('no_discount')->default(false)->after('referral_percentage');
            }
        });
    }
};