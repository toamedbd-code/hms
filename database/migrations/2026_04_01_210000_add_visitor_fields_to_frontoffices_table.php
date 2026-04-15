<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('frontoffices', function (Blueprint $table) {
            if (!Schema::hasColumn('frontoffices', 'visit_to')) {
                $table->string('visit_to')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('frontoffices', 'date_in')) {
                $table->date('date_in')->nullable()->after('visit_to');
            }
            if (!Schema::hasColumn('frontoffices', 'time_in')) {
                $table->time('time_in')->nullable()->after('date_in');
            }
            if (!Schema::hasColumn('frontoffices', 'time_out')) {
                $table->time('time_out')->nullable()->after('time_in');
            }
        });
    }

    public function down(): void
    {
        Schema::table('frontoffices', function (Blueprint $table) {
            $dropColumns = array_filter([
                Schema::hasColumn('frontoffices', 'time_out') ? 'time_out' : null,
                Schema::hasColumn('frontoffices', 'time_in') ? 'time_in' : null,
                Schema::hasColumn('frontoffices', 'date_in') ? 'date_in' : null,
                Schema::hasColumn('frontoffices', 'visit_to') ? 'visit_to' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
