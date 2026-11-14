<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->where('guard_name', 'admin')
            ->whereIn('name', ['Admin', 'developer'])
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        $categoryNames = DB::table('testcategories')
            ->whereNull('deleted_at')
            ->pluck('name')
            ->unique();

        foreach ($categoryNames as $categoryName) {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($categoryName))), '-');
            if ($slug === '') {
                continue;
            }

            $permissionId = DB::table('permissions')
                ->where('name', 'dashboard-card-' . $slug . '-income')
                ->where('guard_name', 'admin')
                ->value('id');

            if ($permissionId) {
                DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->whereIn('role_id', $roleIds)
                    ->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep category access explicit; permissions are reassigned from the role editor.
    }
};
