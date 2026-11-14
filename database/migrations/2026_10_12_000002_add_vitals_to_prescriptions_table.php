<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prescriptions')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('prescriptions', 'nibp')) {
                $table->string('nibp')->nullable();
            }
            if (!Schema::hasColumn('prescriptions', 'pulse')) {
                $table->string('pulse')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('prescriptions')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('prescriptions', 'nibp')) {
                $columns[] = 'nibp';
            }
            if (Schema::hasColumn('prescriptions', 'pulse')) {
                $columns[] = 'pulse';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
