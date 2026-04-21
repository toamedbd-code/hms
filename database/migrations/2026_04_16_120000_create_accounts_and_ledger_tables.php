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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->enum('type', ['asset','liability','equity','income','expense'])->default('asset');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('accounts')->onDelete('set null');
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->date('date')->nullable();
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 15, 2);
            $table->enum('entry_type', ['debit','credit']);
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('ledger_transactions')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('accounts');
    }
};
