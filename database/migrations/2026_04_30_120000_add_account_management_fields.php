<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 2)->nullable()->after('description');
            }
            if (!Schema::hasColumn('accounts', 'opening_balance_type')) {
                $table->enum('opening_balance_type', ['debit','credit'])->nullable()->after('opening_balance');
            }
            if (!Schema::hasColumn('accounts', 'account_group')) {
                $table->string('account_group')->nullable()->after('opening_balance_type');
            }
            if (!Schema::hasColumn('accounts', 'is_profit_loss')) {
                $table->boolean('is_profit_loss')->default(false)->after('is_active');
            }
        });

        Schema::table('account_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('account_balances', 'profit')) {
                $table->decimal('profit', 15, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('account_balances', 'loss')) {
                $table->decimal('loss', 15, 2)->default(0)->after('profit');
            }
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_entries', 'narration')) {
                $table->text('narration')->nullable()->after('entry_type');
            }
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_transactions', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('reference_id');
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entries', 'posted_by')) {
                $table->unsignedBigInteger('posted_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('journal_entries', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('posted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_transactions', 'journal_entry_id')) {
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn('journal_entry_id');
            }
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_entries', 'narration')) {
                $table->dropColumn('narration');
            }
        });

        Schema::table('account_balances', function (Blueprint $table) {
            if (Schema::hasColumn('account_balances', 'profit')) {
                $table->dropColumn('profit');
            }
            if (Schema::hasColumn('account_balances', 'loss')) {
                $table->dropColumn('loss');
            }
        });

        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'opening_balance')) {
                $table->dropColumn('opening_balance');
            }
            if (Schema::hasColumn('accounts', 'opening_balance_type')) {
                $table->dropColumn('opening_balance_type');
            }
            if (Schema::hasColumn('accounts', 'account_group')) {
                $table->dropColumn('account_group');
            }
            if (Schema::hasColumn('accounts', 'is_profit_loss')) {
                $table->dropColumn('is_profit_loss');
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'posted_at')) {
                $table->dropColumn('posted_at');
            }
            if (Schema::hasColumn('journal_entries', 'posted_by')) {
                $table->dropColumn('posted_by');
            }
        });
    }
};
