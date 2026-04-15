<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'pathologist_name')) {
                $table->string('pathologist_name')->nullable()->after('pathologist_signature');
            }

            if (!Schema::hasColumn('web_settings', 'pathologist_designation')) {
                $table->string('pathologist_designation')->nullable()->after('pathologist_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'pathologist_designation')) {
                $table->dropColumn('pathologist_designation');
            }

            if (Schema::hasColumn('web_settings', 'pathologist_name')) {
                $table->dropColumn('pathologist_name');
            }
        });
    }
};
