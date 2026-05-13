<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->nullable()->unique();
            $table->string('name');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('cost', 16, 2)->default(0);
            $table->decimal('salvage_value', 16, 2)->default(0);
            $table->integer('useful_life_months')->nullable();
            $table->string('depreciation_method')->default('straight_line');
            $table->decimal('accumulated_depreciation', 16, 2)->default(0);
            $table->decimal('net_book_value', 16, 2)->default(0);
            $table->string('location')->nullable();
            $table->enum('status', ['active','disposed'])->default('active');
            $table->timestamp('disposed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
