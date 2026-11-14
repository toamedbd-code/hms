<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoicedesigns') && !Schema::hasColumn('invoicedesigns', 'full_page_pad_a5_bottom_inset')) {
            Schema::table('invoicedesigns', function (Blueprint $table) {
                $table->unsignedTinyInteger('full_page_pad_a5_bottom_inset')->default(5);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoicedesigns') && Schema::hasColumn('invoicedesigns', 'full_page_pad_a5_bottom_inset')) {
            Schema::table('invoicedesigns', function (Blueprint $table) {
                $table->dropColumn('full_page_pad_a5_bottom_inset');
            });
        }
    }
};
