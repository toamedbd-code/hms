<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('birthdeathrecords', function (Blueprint $table) {
            if (!Schema::hasColumn('birthdeathrecords', 'child_name')) {
                $table->string('child_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'patient_name')) {
                $table->string('patient_name')->nullable()->after('child_name');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'weight')) {
                $table->string('weight', 60)->nullable()->after('gender');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('record_date');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'death_date')) {
                $table->date('death_date')->nullable()->after('birth_date');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'phone')) {
                $table->string('phone', 30)->nullable()->after('death_date');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'case_id')) {
                $table->string('case_id', 120)->nullable()->after('address');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'mother_name')) {
                $table->string('mother_name')->nullable()->after('case_id');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'father_name')) {
                $table->string('father_name')->nullable()->after('mother_name');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'report')) {
                $table->text('report')->nullable()->after('father_name');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'child_photo')) {
                $table->string('child_photo')->nullable()->after('report');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'mother_photo')) {
                $table->string('mother_photo')->nullable()->after('child_photo');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'father_photo')) {
                $table->string('father_photo')->nullable()->after('mother_photo');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'attachment')) {
                $table->string('attachment')->nullable()->after('father_photo');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'report_attachment')) {
                $table->string('report_attachment')->nullable()->after('attachment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('birthdeathrecords', function (Blueprint $table) {
            $dropColumns = array_filter([
                Schema::hasColumn('birthdeathrecords', 'report_attachment') ? 'report_attachment' : null,
                Schema::hasColumn('birthdeathrecords', 'attachment') ? 'attachment' : null,
                Schema::hasColumn('birthdeathrecords', 'father_photo') ? 'father_photo' : null,
                Schema::hasColumn('birthdeathrecords', 'mother_photo') ? 'mother_photo' : null,
                Schema::hasColumn('birthdeathrecords', 'child_photo') ? 'child_photo' : null,
                Schema::hasColumn('birthdeathrecords', 'report') ? 'report' : null,
                Schema::hasColumn('birthdeathrecords', 'father_name') ? 'father_name' : null,
                Schema::hasColumn('birthdeathrecords', 'mother_name') ? 'mother_name' : null,
                Schema::hasColumn('birthdeathrecords', 'case_id') ? 'case_id' : null,
                Schema::hasColumn('birthdeathrecords', 'address') ? 'address' : null,
                Schema::hasColumn('birthdeathrecords', 'phone') ? 'phone' : null,
                Schema::hasColumn('birthdeathrecords', 'death_date') ? 'death_date' : null,
                Schema::hasColumn('birthdeathrecords', 'birth_date') ? 'birth_date' : null,
                Schema::hasColumn('birthdeathrecords', 'weight') ? 'weight' : null,
                Schema::hasColumn('birthdeathrecords', 'patient_name') ? 'patient_name' : null,
                Schema::hasColumn('birthdeathrecords', 'child_name') ? 'child_name' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
