<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expenses', 'department_id')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('expense_header_id')
                ->constrained('departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('expenses', 'department_id')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
