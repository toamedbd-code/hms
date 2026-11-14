<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tests') && Schema::hasColumn('tests', 'test_category_id')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->unsignedBigInteger('test_category_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tests') && Schema::hasColumn('tests', 'test_category_id')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->unsignedBigInteger('test_category_id')->nullable(false)->change();
            });
        }
    }
};
