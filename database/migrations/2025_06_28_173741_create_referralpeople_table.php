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
        Schema::create('referralpeople', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('referralcategories')->onDelete('cascade');
            $table->string('address')->nullable();
            $table->decimal('standard_commission', 5, 2)->nullable()->default(0.00);
            $table->decimal('opd_commission', 5, 2)->nullable()->default(0.00);
            $table->decimal('ipd_commission', 5, 2)->nullable()->default(0.00);
            $table->decimal('pharmacy_commission', 5, 2)->nullable()->default(0.00);
            $table->decimal('pathology_commission', 5, 2)->nullable()->default(0.00);
            $table->decimal('radiology_commission', 5, 2)->nullable()->default(0.00);
            $table->decimal('blood_bank_commission', 5, 2)->nullable()->default(0.00);
            $table->decimal('ambulance_commission', 5, 2)->nullable()->default(0.00);
            $table->boolean('apply_to_all')->default(false);
            
            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referralpeople');
    }
};