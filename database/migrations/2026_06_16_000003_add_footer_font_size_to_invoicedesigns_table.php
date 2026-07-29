<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicedesigns', function (Blueprint $table) {
            if (! Schema::hasColumn('invoicedesigns', 'footer_font_size')) {
                $table->integer('footer_font_size')->nullable()->after('footer_height');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicedesigns', function (Blueprint $table) {
            if (Schema::hasColumn('invoicedesigns', 'footer_font_size')) {
                $table->dropColumn('footer_font_size');
            }
        });
    }
};
