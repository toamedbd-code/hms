<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_counter_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('counter_name');
            $table->string('user_name');
            $table->string('shift_name')->nullable();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->decimal('expected_amount', 12, 2)->default(0);
            $table->decimal('closing_amount', 12, 2)->default(0);
            $table->decimal('difference_amount', 12, 2)->default(0);
            $table->decimal('handover_in_amount', 12, 2)->default(0);
            $table->decimal('handover_out_amount', 12, 2)->default(0);
            $table->text('opening_note')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_counter_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_counter_session_id')->constrained('cash_counter_sessions')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counter_transactions');
        Schema::dropIfExists('cash_counter_sessions');
    }
};
