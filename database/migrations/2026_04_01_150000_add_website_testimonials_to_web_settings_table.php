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
            $table->longText('website_testimonials_en_json')->nullable()->after('website_facilities_json');
            $table->longText('website_testimonials_bn_json')->nullable()->after('website_testimonials_en_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn(['website_testimonials_en_json', 'website_testimonials_bn_json']);
        });
    }
};
