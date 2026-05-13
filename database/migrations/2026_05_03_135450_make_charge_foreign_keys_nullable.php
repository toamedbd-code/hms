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
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedBigInteger('charge_type_id')->nullable()->change();
            $table->unsignedBigInteger('charge_category_id')->nullable()->change();
            $table->unsignedBigInteger('unit_type_id')->nullable()->change();
            $table->unsignedBigInteger('tax_category_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedBigInteger('charge_type_id')->nullable(false)->change();
            $table->unsignedBigInteger('charge_category_id')->nullable(false)->change();
            $table->unsignedBigInteger('unit_type_id')->nullable(false)->change();
            $table->unsignedBigInteger('tax_category_id')->nullable(false)->change();
        });
    }
};
