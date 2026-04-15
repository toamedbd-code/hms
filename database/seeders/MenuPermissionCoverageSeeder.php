<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuPermissionCoverageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $permissionsByName = Permission::query()->pluck('id', 'name');

            $menus = Menu::query()
                ->select(['id', 'parent_id', 'permission_name'])
                ->whereNotNull('permission_name')
                ->where('permission_name', '!=', '')
                ->orderBy('parent_id')
                ->orderBy('id')
                ->get();

            foreach ($menus as $menu) {
                $permissionName = trim((string) $menu->permission_name);
                if ($permissionName === '') {
                    continue;
                }

                $parentPermissionId = null;
                if (!empty($menu->parent_id)) {
                    $parentMenu = $menus->firstWhere('id', $menu->parent_id);
                    if ($parentMenu && !empty($parentMenu->permission_name)) {
                        $parentPermissionName = trim((string) $parentMenu->permission_name);
                        $parentPermissionId = $permissionsByName[$parentPermissionName] ?? null;

                        if ($parentPermissionId === null && $parentPermissionName !== '') {
                            $createdParent = Permission::query()->firstOrCreate([
                                'name' => $parentPermissionName,
                                'guard_name' => 'admin',
                            ]);
                            $parentPermissionId = $createdParent->id;
                            $permissionsByName[$parentPermissionName] = $createdParent->id;
                        }
                    }
                }

                $permission = Permission::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'admin',
                ]);

                if ($parentPermissionId && (int) $permission->parent_id !== (int) $parentPermissionId) {
                    $permission->parent_id = $parentPermissionId;
                    $permission->save();
                }

                $permissionsByName[$permissionName] = $permission->id;
            }

            $webSettingPermission = Permission::query()->firstOrCreate([
                'name' => 'websetting-add',
                'guard_name' => 'admin',
            ]);

            Permission::query()->firstOrCreate(
                [
                    'name' => 'machine-integration-setting',
                    'guard_name' => 'admin',
                ],
                [
                    'parent_id' => $webSettingPermission->id,
                ]
            );
        });
    }
}
