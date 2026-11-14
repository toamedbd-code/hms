<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $mappings = [
            ['websetting-add', 'report-settings'],
            ['item-charge', 'charge-list'],
            ['hospital-test', 'charge-list'],
        ];

        foreach ($mappings as [$legacyName, $requiredName]) {
            $legacyPermissionId = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->where('name', $legacyName)
                ->value('id');
            $requiredPermissionId = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->where('name', $requiredName)
                ->value('id');

            if (!$legacyPermissionId || !$requiredPermissionId) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $legacyPermissionId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $requiredPermissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
    }
};
