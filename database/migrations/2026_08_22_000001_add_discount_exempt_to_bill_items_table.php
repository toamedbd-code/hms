<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            if (!Schema::hasColumn('bill_items', 'discount_exempt')) {
                $table->boolean('discount_exempt')->default(false)->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            if (Schema::hasColumn('bill_items', 'discount_exempt')) {
                $table->dropColumn('discount_exempt');
            }
        });
    }
};