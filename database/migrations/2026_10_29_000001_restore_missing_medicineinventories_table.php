<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('medicineinventories')) {
            return;
        }

        Schema::create('medicineinventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('medicine_category_id');
            $table->string('medicine_name');
            $table->string('generic_name')->nullable();
            $table->decimal('medicine_unit_purchase_price', 11, 2);
            $table->decimal('medicine_unit_selling_price', 11, 2);
            $table->decimal('medicine_total_purchase_price', 11, 2);
            $table->decimal('medicine_total_selling_price', 11, 2);
            $table->integer('medicine_quantity');
            $table->date('expiry_date')->nullable();
            $table->string('standard_dose')->nullable();
            $table->string('standard_frequency')->nullable();
            $table->string('standard_duration')->nullable();
            $table->string('standard_instructions')->nullable();
            $table->boolean('standard_prescription_verified')->default(false);
            $table->text('remarks')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'expiry_date'], 'medinv_status_expiry_idx');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('CREATE FULLTEXT INDEX medinv_name_fulltext_idx ON medicineinventories (medicine_name)');
            DB::statement('CREATE INDEX medinv_name_prefix_idx ON medicineinventories (medicine_name(191))');
        } else {
            Schema::table('medicineinventories', function (Blueprint $table) {
                $table->index('medicine_name', 'medinv_name_prefix_idx');
            });
        }
    }

    public function down(): void
    {
        // Keep restored inventory data when rolling back unrelated migrations.
    }
};
