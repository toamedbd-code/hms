<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bill_items') && !Schema::hasColumn('bill_items', 'department_id')) {
            Schema::table('bill_items', function (Blueprint $table) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('billing_id')
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bill_items') && Schema::hasColumn('bill_items', 'department_id')) {
            Schema::table('bill_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }
    }
};