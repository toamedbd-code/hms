<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'opd_invoice_header_footer')) {
                $table->boolean('opd_invoice_header_footer')->default(false)->after('sms_is_unicode');
            }
            if (!Schema::hasColumn('web_settings', 'ipd_invoice_header_footer')) {
                $table->boolean('ipd_invoice_header_footer')->default(false)->after('opd_invoice_header_footer');
            }
            if (!Schema::hasColumn('web_settings', 'opd_prescription_header_footer')) {
                $table->boolean('opd_prescription_header_footer')->default(false)->after('ipd_invoice_header_footer');
            }
            if (!Schema::hasColumn('web_settings', 'ipd_prescription_header_footer')) {
                $table->boolean('ipd_prescription_header_footer')->default(false)->after('opd_prescription_header_footer');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('web_settings', 'opd_invoice_header_footer')) {
                $drop[] = 'opd_invoice_header_footer';
            }
            if (Schema::hasColumn('web_settings', 'ipd_invoice_header_footer')) {
                $drop[] = 'ipd_invoice_header_footer';
            }
            if (Schema::hasColumn('web_settings', 'opd_prescription_header_footer')) {
                $drop[] = 'opd_prescription_header_footer';
            }
            if (Schema::hasColumn('web_settings', 'ipd_prescription_header_footer')) {
                $drop[] = 'ipd_prescription_header_footer';
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
