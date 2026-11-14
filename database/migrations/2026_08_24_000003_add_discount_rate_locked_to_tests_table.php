<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tests', 'discount_rate_locked')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->boolean('discount_rate_locked')->default(false)->after('discount_percentage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tests', 'discount_rate_locked')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->dropColumn('discount_rate_locked');
            });
        }
    }
};
