<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoicedesigns')) {
            return;
        }

        Schema::table('invoicedesigns', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicedesigns', 'full_page_pad_path')) {
                $table->string('full_page_pad_path')->nullable();
            }
            if (!Schema::hasColumn('invoicedesigns', 'active_pad_type')) {
                $table->string('active_pad_type')->default('header');
            }
            if (!Schema::hasColumn('invoicedesigns', 'full_page_pad_content_offset')) {
                $table->unsignedSmallInteger('full_page_pad_content_offset')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoicedesigns')) {
            return;
        }

        Schema::table('invoicedesigns', function (Blueprint $table) {
            $columns = [];
            foreach (['full_page_pad_path', 'active_pad_type', 'full_page_pad_content_offset'] as $column) {
                if (Schema::hasColumn('invoicedesigns', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
