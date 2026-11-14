<?php

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_counter_sessions') && !Schema::hasColumn('cash_counter_sessions', 'ledger_close_transaction_id')) {
            Schema::table('cash_counter_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('ledger_close_transaction_id')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('salary_payments') && !Schema::hasColumn('salary_payments', 'ledger_transaction_id')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('ledger_transaction_id')->nullable()->after('status');
            });
        }

        if (!Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->date('statement_date');
                $table->decimal('statement_balance', 14, 2)->default(0);
                $table->decimal('book_balance', 14, 2)->default(0);
                $table->decimal('cleared_balance', 14, 2)->default(0);
                $table->decimal('difference', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('completed');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'statement_date']);
            });
        }

        if (!Schema::hasTable('bank_reconciliation_items')) {
            Schema::create('bank_reconciliation_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bank_reconciliation_id');
                $table->unsignedBigInteger('ledger_entry_id');
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('entry_type', 10);
                $table->boolean('is_cleared')->default(true);
                $table->timestamps();

                $table->index(['bank_reconciliation_id', 'ledger_entry_id'], 'bank_recon_items_idx');
            });
        }

        $accountManagement = Permission::firstOrCreate(
            ['name' => 'account-management', 'guard_name' => 'admin'],
            ['sorting' => 1]
        );

        $permission = Permission::firstOrCreate(
            ['name' => 'bank-reconciliation', 'guard_name' => 'admin'],
            ['parent_id' => $accountManagement->id, 'sorting' => 1]
        );

        foreach (['developer', 'Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'admin')->first();
            if ($role) {
                $role->givePermissionTo($permission);
            }
        }

        $parentMenu = Menu::query()
            ->where('route', 'backend.accounts.index')
            ->orWhere('name', 'Account Management')
            ->orderByRaw("CASE WHEN route = 'backend.accounts.index' THEN 0 ELSE 1 END")
            ->first();

        if ($parentMenu && !Menu::where('route', 'backend.accounts.bank-reconciliation.index')->exists()) {
            Menu::create([
                'name' => 'Bank Reconciliation',
                'icon' => 'credit-card',
                'route' => 'backend.accounts.bank-reconciliation.index',
                'parent_id' => $parentMenu->id,
                'description' => null,
                'sorting' => 1,
                'permission_name' => 'bank-reconciliation',
                'status' => 'Active',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_items');
        Schema::dropIfExists('bank_reconciliations');

        if (Schema::hasTable('cash_counter_sessions') && Schema::hasColumn('cash_counter_sessions', 'ledger_close_transaction_id')) {
            Schema::table('cash_counter_sessions', function (Blueprint $table) {
                $table->dropColumn('ledger_close_transaction_id');
            });
        }

        if (Schema::hasTable('salary_payments') && Schema::hasColumn('salary_payments', 'ledger_transaction_id')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->dropColumn('ledger_transaction_id');
            });
        }
    }
};
