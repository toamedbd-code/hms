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
        Schema::table('billings', function (Blueprint $table) {
            if (!Schema::hasColumn('billings', 'return_amt')) {
                $table->decimal('return_amt', 10, 2)->default(0)->after('receiving_amt');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            if (Schema::hasColumn('billings', 'return_amt')) {
                $table->dropColumn('return_amt');
            }
        });
    }
};
