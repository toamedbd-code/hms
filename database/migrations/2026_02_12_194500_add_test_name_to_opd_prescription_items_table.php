<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opd_prescription_items', function (Blueprint $table) {
            if (!Schema::hasColumn('opd_prescription_items', 'test_name')) {
                $table->string('test_name')->nullable()->after('opd_prescription_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opd_prescription_items', function (Blueprint $table) {
            if (Schema::hasColumn('opd_prescription_items', 'test_name')) {
                $table->dropColumn('test_name');
            }
        });
    }
};
