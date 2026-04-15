<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('frontoffices', function (Blueprint $table) {
            if (!Schema::hasColumn('frontoffices', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('frontoffices', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            if (!Schema::hasColumn('frontoffices', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
            if (!Schema::hasColumn('frontoffices', 'purpose')) {
                $table->string('purpose')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('frontoffices', 'visit_date')) {
                $table->date('visit_date')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('frontoffices', 'notes')) {
                $table->text('notes')->nullable()->after('visit_date');
            }
            if (!Schema::hasColumn('frontoffices', 'photo')) {
                $table->string('photo')->nullable()->after('notes');
            }
        });

        Schema::table('birthdeathrecords', function (Blueprint $table) {
            if (!Schema::hasColumn('birthdeathrecords', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'record_type')) {
                $table->enum('record_type', ['Birth', 'Death'])->nullable()->after('name');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'record_date')) {
                $table->date('record_date')->nullable()->after('record_type');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'guardian_name')) {
                $table->string('guardian_name')->nullable()->after('record_date');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'gender')) {
                $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->after('guardian_name');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'email')) {
                $table->string('email')->nullable()->after('gender');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'notes')) {
                $table->text('notes')->nullable()->after('email');
            }
            if (!Schema::hasColumn('birthdeathrecords', 'photo')) {
                $table->string('photo')->nullable()->after('notes');
            }
        });

        Schema::table('certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('certificates', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('certificates', 'certificate_type')) {
                $table->string('certificate_type')->nullable()->after('name');
            }
            if (!Schema::hasColumn('certificates', 'issue_date')) {
                $table->date('issue_date')->nullable()->after('certificate_type');
            }
            if (!Schema::hasColumn('certificates', 'reference_no')) {
                $table->string('reference_no', 120)->nullable()->after('issue_date');
            }
            if (!Schema::hasColumn('certificates', 'email')) {
                $table->string('email')->nullable()->after('reference_no');
            }
            if (!Schema::hasColumn('certificates', 'details')) {
                $table->text('details')->nullable()->after('email');
            }
            if (!Schema::hasColumn('certificates', 'photo')) {
                $table->string('photo')->nullable()->after('details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('frontoffices', function (Blueprint $table) {
            $dropColumns = array_filter([
                Schema::hasColumn('frontoffices', 'photo') ? 'photo' : null,
                Schema::hasColumn('frontoffices', 'notes') ? 'notes' : null,
                Schema::hasColumn('frontoffices', 'visit_date') ? 'visit_date' : null,
                Schema::hasColumn('frontoffices', 'purpose') ? 'purpose' : null,
                Schema::hasColumn('frontoffices', 'phone') ? 'phone' : null,
                Schema::hasColumn('frontoffices', 'email') ? 'email' : null,
                Schema::hasColumn('frontoffices', 'name') ? 'name' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('birthdeathrecords', function (Blueprint $table) {
            $dropColumns = array_filter([
                Schema::hasColumn('birthdeathrecords', 'photo') ? 'photo' : null,
                Schema::hasColumn('birthdeathrecords', 'notes') ? 'notes' : null,
                Schema::hasColumn('birthdeathrecords', 'email') ? 'email' : null,
                Schema::hasColumn('birthdeathrecords', 'gender') ? 'gender' : null,
                Schema::hasColumn('birthdeathrecords', 'guardian_name') ? 'guardian_name' : null,
                Schema::hasColumn('birthdeathrecords', 'record_date') ? 'record_date' : null,
                Schema::hasColumn('birthdeathrecords', 'record_type') ? 'record_type' : null,
                Schema::hasColumn('birthdeathrecords', 'name') ? 'name' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('certificates', function (Blueprint $table) {
            $dropColumns = array_filter([
                Schema::hasColumn('certificates', 'photo') ? 'photo' : null,
                Schema::hasColumn('certificates', 'details') ? 'details' : null,
                Schema::hasColumn('certificates', 'email') ? 'email' : null,
                Schema::hasColumn('certificates', 'reference_no') ? 'reference_no' : null,
                Schema::hasColumn('certificates', 'issue_date') ? 'issue_date' : null,
                Schema::hasColumn('certificates', 'certificate_type') ? 'certificate_type' : null,
                Schema::hasColumn('certificates', 'name') ? 'name' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
