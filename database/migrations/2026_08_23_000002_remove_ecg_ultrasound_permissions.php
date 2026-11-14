<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = collect();

        $rootIds = DB::table('permissions')
            ->whereIn('name', [
                'ecg-management',
                'ultrasound-management',
                'ecg-list',
                'ultrasound-list',
            ])
            ->pluck('id');

        $pendingIds = $rootIds->values();
        while ($pendingIds->isNotEmpty()) {
            $newIds = $pendingIds->diff($permissionIds);
            if ($newIds->isEmpty()) {
                break;
            }

            $permissionIds = $permissionIds->merge($newIds)->unique()->values();
            $pendingIds = DB::table('permissions')
                ->whereIn('parent_id', $newIds->all())
                ->pluck('id');
        }

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds->all())
                ->delete();
            DB::table('model_has_permissions')
                ->whereIn('permission_id', $permissionIds->all())
                ->delete();
            DB::table('permissions')
                ->whereIn('id', $permissionIds->all())
                ->delete();
        }

        $menuIds = DB::table('menus')
            ->whereIn('name', ['ECG', 'ECG List', 'Ultrasound', 'Ultrasound List'])
            ->pluck('id');

        if ($menuIds->isNotEmpty()) {
            DB::table('menus')
                ->whereIn('parent_id', $menuIds->all())
                ->delete();
            DB::table('menus')
                ->whereIn('id', $menuIds->all())
                ->delete();
        }
    }

    public function down(): void
    {
        // Removed menus and permissions can be restored only by a prior data backup.
    }
};
