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
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'website_hero_title')) {
                $table->string('website_hero_title', 255)->nullable()->after('report_title');
            }

            if (!Schema::hasColumn('web_settings', 'website_hero_subtitle')) {
                $table->text('website_hero_subtitle')->nullable()->after('website_hero_title');
            }

            if (!Schema::hasColumn('web_settings', 'website_about_text')) {
                $table->text('website_about_text')->nullable()->after('website_hero_subtitle');
            }

            if (!Schema::hasColumn('web_settings', 'website_emergency_phone')) {
                $table->string('website_emergency_phone', 100)->nullable()->after('website_about_text');
            }

            if (!Schema::hasColumn('web_settings', 'website_cta_text')) {
                $table->string('website_cta_text', 255)->nullable()->after('website_emergency_phone');
            }

            if (!Schema::hasColumn('web_settings', 'website_featured_doctors_json')) {
                $table->longText('website_featured_doctors_json')->nullable()->after('website_cta_text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $columns = [
                'website_hero_title',
                'website_hero_subtitle',
                'website_about_text',
                'website_emergency_phone',
                'website_cta_text',
                'website_featured_doctors_json',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('web_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
