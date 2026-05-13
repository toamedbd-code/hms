<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('name', 255);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module', 'company_id', 'branch_id', 'is_active'], 'approval_flows_scope_idx');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('approval_flow_id');
            $table->unsignedInteger('step_no');
            $table->enum('approver_type', ['role', 'user']);
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->unsignedInteger('min_approvals')->default(1);
            $table->decimal('amount_threshold_min', 16, 2)->nullable();
            $table->decimal('amount_threshold_max', 16, 2)->nullable();
            $table->timestamps();

            $table->unique(['approval_flow_id', 'step_no']);
            $table->index(['approver_type', 'approver_role_id', 'approver_user_id'], 'approval_steps_approver_idx');
            $table->foreign('approval_flow_id')->references('id')->on('approval_flows')->onDelete('cascade');
            $table->foreign('approver_role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('approver_user_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('entity_type', 150);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('approval_flow_id')->nullable();
            $table->unsignedInteger('current_step_no')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['module', 'status', 'company_id', 'branch_id'], 'approval_requests_scope_idx');
            $table->index(['entity_type', 'entity_id'], 'approval_requests_entity_idx');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('approval_flow_id')->references('id')->on('approval_flows')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('approval_request_id');
            $table->unsignedInteger('step_no')->nullable();
            $table->enum('action', ['approve', 'reject', 'return', 'cancel']);
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['approval_request_id', 'step_no', 'action'], 'approval_actions_step_idx');
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')->onDelete('cascade');
            $table->foreign('acted_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_flows');
    }
};
