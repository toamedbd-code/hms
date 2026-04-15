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
        Schema::table('opdpatients', function (Blueprint $table) {
            if (!Schema::hasColumn('opdpatients', 'nibp')) {
                $table->string('nibp')->nullable()->after('allergies');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opdpatients', function (Blueprint $table) {
            if (Schema::hasColumn('opdpatients', 'nibp')) {
                $table->dropColumn('nibp');
            }
        });
    }
};
