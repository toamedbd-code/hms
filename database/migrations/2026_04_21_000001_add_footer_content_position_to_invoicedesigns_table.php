<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicedesigns', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicedesigns', 'footer_content_position')) {
                $table->enum('footer_content_position', ['above', 'below'])->default('above')->nullable()->after('footer_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicedesigns', function (Blueprint $table) {
            if (Schema::hasColumn('invoicedesigns', 'footer_content_position')) {
                $table->dropColumn('footer_content_position');
            }
        });
    }
};
