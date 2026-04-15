<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'sms_enabled')) {
                $table->boolean('sms_enabled')->default(false)->after('current_theme');
            }
            if (!Schema::hasColumn('web_settings', 'sms_api_url')) {
                $table->string('sms_api_url', 255)->nullable()->after('sms_enabled');
            }
            if (!Schema::hasColumn('web_settings', 'sms_api_key')) {
                $table->string('sms_api_key', 255)->nullable()->after('sms_api_url');
            }
            if (!Schema::hasColumn('web_settings', 'sms_sender_id')) {
                $table->string('sms_sender_id', 100)->nullable()->after('sms_api_key');
            }
            if (!Schema::hasColumn('web_settings', 'sms_route')) {
                $table->string('sms_route', 50)->nullable()->after('sms_sender_id');
            }
            if (!Schema::hasColumn('web_settings', 'sms_is_unicode')) {
                $table->boolean('sms_is_unicode')->default(false)->after('sms_route');
            }
            if (!Schema::hasColumn('web_settings', 'sms_additional_params')) {
                $table->text('sms_additional_params')->nullable()->after('sms_is_unicode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $columns = [
                'sms_additional_params',
                'sms_is_unicode',
                'sms_route',
                'sms_sender_id',
                'sms_api_key',
                'sms_api_url',
                'sms_enabled',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('web_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
