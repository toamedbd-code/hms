<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('web_settings', 'pharmacy_tax_number')) {
            Schema::table('web_settings', function (Blueprint $table) {
                $table->string('pharmacy_tax_number', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('web_settings', 'pharmacy_tax_number')) {
            Schema::table('web_settings', function (Blueprint $table) {
                $table->dropColumn('pharmacy_tax_number');
            });
        }
    }
};
