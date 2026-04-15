<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('store_requisitions')) {
            return;
        }

        Schema::create('store_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_no', 60)->unique();
            $table->string('department', 100);
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->date('needed_date')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_requisitions');
    }
};
