<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_postings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->boolean('is_repost')->default(false);
            $table->date('posting_date')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->integer('line_count')->default(0);
            $table->json('snapshot')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('admins')->nullOnDelete();

            $table->index(['posting_date', 'id'], 'opening_balance_postings_date_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_postings');
    }
};
