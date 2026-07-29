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
        // Add indexes to speed up login queries
        if (Schema::hasTable('admins')) {
            Schema::table('admins', function (Blueprint $table) {
                // Index on email for faster login lookups
                if (!Schema::hasColumn('admins', 'email') || !$this->indexExists('admins', 'email')) {
                    $table->index('email')->change();
                }
                // Index on phone for alternate login method
                if (!Schema::hasColumn('admins', 'phone') || !$this->indexExists('admins', 'phone')) {
                    $table->index('phone')->change();
                }
                // Composite index for status check during login
                if (!$this->indexExists('admins', 'status_deleted_at')) {
                    $table->index(['status', 'deleted_at']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('admins')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropIndex('admins_email_index');
                $table->dropIndex('admins_phone_index');
                $table->dropIndex('admins_status_deleted_at_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM $table WHERE Key_name = ?", [$index]);
            return count($indexes) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
