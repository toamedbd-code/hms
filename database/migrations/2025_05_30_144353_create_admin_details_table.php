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
        Schema::create('admin_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');

            $table->string('staff_id')->nullable()->unique();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable();
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->string('emergency_contact')->nullable();

            $table->unsignedBigInteger('designation_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('specialist_id')->nullable();

            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();

            $table->string('pan_number')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('local_id_number')->nullable();

            $table->text('qualification')->nullable();
            $table->text('work_experience')->nullable();
            $table->text('specialization')->nullable();
            $table->text('note')->nullable();

            $table->string('epf_no')->nullable();
            $table->decimal('basic_salary', 10, 2)->nullable();
            $table->enum('contract_type', ['Permanent', 'Probation'])->nullable();
            $table->string('work_shift')->nullable();
            $table->string('work_location')->nullable();

            $table->integer('number_of_leaves')->nullable();

            $table->string('bank_account_title')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch_name')->nullable();
            $table->string('ifsc_code')->nullable();

            $table->string('facebook_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();

            $table->string('resume_path')->nullable();
            $table->string('joining_letter_path')->nullable();
            $table->string('resignation_letter_path')->nullable();
            $table->string('other_documents_path')->nullable();

            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('designation_id')->references('id')->on('designations')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('set null');

            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admin_details');
    }
};
