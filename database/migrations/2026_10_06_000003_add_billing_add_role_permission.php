<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $billingPermission = DB::table('permissions')
            ->where('name', 'billing')
            ->where('guard_name', 'admin')
            ->first();

        if (!$billingPermission) {
            $billingPermissionId = DB::table('permissions')->insertGetId([
                'name' => 'billing',
                'guard_name' => 'admin',
                'parent_id' => null,
                'sorting' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $billingPermission = (object) ['id' => $billingPermissionId];
        }

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'billing-add', 'guard_name' => 'admin'],
            [
                'parent_id' => $billingPermission->id,
                'sorting' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $addPermission = DB::table('permissions')
            ->where('name', 'billing-add')
            ->where('guard_name', 'admin')
            ->first();

        DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->where('name', 'Add Bill')
            ->update([
                'permission_name' => 'billing-add',
                'parent_id' => null,
                'updated_at' => $now,
            ]);

        foreach (DB::table('role_has_permissions')->where('permission_id', $billingPermission->id)->get() as $assignment) {
            $pivot = (array) $assignment;
            $pivot['permission_id'] = $addPermission->id;
            DB::table('role_has_permissions')->insertOrIgnore($pivot);
        }

        $modelPermissionsTable = config('permission.table_names.model_has_permissions');
        if ($modelPermissionsTable && DB::getSchemaBuilder()->hasTable($modelPermissionsTable)) {
            foreach (DB::table($modelPermissionsTable)->where('permission_id', $billingPermission->id)->get() as $assignment) {
                $pivot = (array) $assignment;
                $pivot['permission_id'] = $addPermission->id;
                DB::table($modelPermissionsTable)->insertOrIgnore($pivot);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = DB::table('permissions')
            ->where('name', 'billing-add')
            ->where('guard_name', 'admin')
            ->first();

        DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->where('name', 'Add Bill')
            ->update([
                'permission_name' => 'billing',
                'updated_at' => now(),
            ]);

        if ($permission) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permission->id)
                ->delete();

            $modelPermissionsTable = config('permission.table_names.model_has_permissions');
            if ($modelPermissionsTable && DB::getSchemaBuilder()->hasTable($modelPermissionsTable)) {
                DB::table($modelPermissionsTable)
                    ->where('permission_id', $permission->id)
                    ->delete();
            }

            DB::table('permissions')->where('id', $permission->id)->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
