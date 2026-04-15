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
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'technologist_signature')) {
                $table->string('technologist_signature')->nullable()->after('report_footer_html');
            }

            if (!Schema::hasColumn('web_settings', 'sample_collected_by_signature')) {
                $table->string('sample_collected_by_signature')->nullable()->after('technologist_signature');
            }

            if (!Schema::hasColumn('web_settings', 'pathologist_signature')) {
                $table->string('pathologist_signature')->nullable()->after('sample_collected_by_signature');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $columns = [
                'technologist_signature',
                'sample_collected_by_signature',
                'pathologist_signature',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('web_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
