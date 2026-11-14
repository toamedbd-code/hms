<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoicedesigns') && !Schema::hasColumn('invoicedesigns', 'footer_content_offset')) {
            Schema::table('invoicedesigns', function (Blueprint $table) {
                $table->smallInteger('footer_content_offset')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoicedesigns') && Schema::hasColumn('invoicedesigns', 'footer_content_offset')) {
            Schema::table('invoicedesigns', function (Blueprint $table) {
                $table->dropColumn('footer_content_offset');
            });
        }
    }
};
