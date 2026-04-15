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
        Schema::table('appoinments', function (Blueprint $table) {
            if (!Schema::hasColumn('appoinments', 'booking_source')) {
                $table->string('booking_source', 30)->default('panel')->after('transaction_id');
            }

            if (!Schema::hasColumn('appoinments', 'website_contact_phone')) {
                $table->string('website_contact_phone', 50)->nullable()->after('booking_source');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appoinments', function (Blueprint $table) {
            if (Schema::hasColumn('appoinments', 'website_contact_phone')) {
                $table->dropColumn('website_contact_phone');
            }

            if (Schema::hasColumn('appoinments', 'booking_source')) {
                $table->dropColumn('booking_source');
            }
        });
    }
};
