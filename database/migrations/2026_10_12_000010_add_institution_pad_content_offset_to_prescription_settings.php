<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescription_settings') && !Schema::hasColumn('prescription_settings', 'institution_pad_content_offset')) {
            Schema::table('prescription_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('institution_pad_content_offset')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prescription_settings') && Schema::hasColumn('prescription_settings', 'institution_pad_content_offset')) {
            Schema::table('prescription_settings', function (Blueprint $table) {
                $table->dropColumn('institution_pad_content_offset');
            });
        }
    }
};
