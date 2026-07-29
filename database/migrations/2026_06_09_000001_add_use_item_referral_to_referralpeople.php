<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('referralpeople', 'use_item_referral')) {
            Schema::table('referralpeople', function (Blueprint $table) {
                $table->boolean('use_item_referral')->default(false)->after('apply_to_all');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('referralpeople', 'use_item_referral')) {
            Schema::table('referralpeople', function (Blueprint $table) {
                $table->dropColumn('use_item_referral');
            });
        }
    }
};
