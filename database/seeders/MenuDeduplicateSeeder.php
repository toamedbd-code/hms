<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuDeduplicateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->removeObsoleteMenus();
            $this->normalizeLegacyRoutes();
            $this->mergeDuplicateRows();
        });
    }

    private function removeObsoleteMenus(): void
    {
        Menu::query()
            ->whereNull('parent_id')
            ->where(function ($query) {
                $query->where('name', 'InvoiceDesign Manage')
                    ->orWhere('permission_name', 'invoicedesign-management');
            })
            ->delete();

        Menu::query()
            ->where('name', 'CMS Setting')
            ->whereIn('route', [
                'backend.websetting.create',
                'websetting.create',
                'backend.websetting.index',
            ])
            ->delete();
    }

    private function normalizeLegacyRoutes(): void
    {
        $aliases = [
            'backend.pharmacy.supplier.payment' => 'backend.supplierpayment.index',
            'admin.attendance.devices' => 'backend.attendance.devices',
        ];

        foreach ($aliases as $from => $to) {
            Menu::query()->where('route', $from)->update(['route' => $to]);
        }
    }

    private function mergeDuplicateRows(): void
    {
        $normalize = static fn ($value): string => strtolower(trim((string) $value));

        $menus = Menu::query()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $keepByKey = [];
        $duplicateIds = [];

        foreach ($menus as $menu) {
            $parentId = $menu->parent_id ?? 0;
            $name = $normalize($menu->name ?? '');
            $permission = $normalize($menu->permission_name ?? '');
            $route = $normalize($menu->route ?? '');

            $keys = [];
            if ($route !== '') {
                $keys[] = sprintf('r:%s:%s', $parentId, $route);
            }
            if ($name !== '') {
                $keys[] = sprintf('n:%s:%s', $parentId, $name);
            }
            if ($permission !== '') {
                $keys[] = sprintf('p:%s:%s', $parentId, $permission);
            }

            if ($keys === []) {
                continue;
            }

            $existingKeepId = null;
            foreach ($keys as $key) {
                if (isset($keepByKey[$key])) {
                    $existingKeepId = $keepByKey[$key];
                    break;
                }
            }

            if ($existingKeepId === null) {
                foreach ($keys as $key) {
                    $keepByKey[$key] = (int) $menu->id;
                }
                continue;
            }

            $keep = Menu::query()->find($existingKeepId);
            if ($keep) {
                if (empty($keep->route) && !empty($menu->route)) {
                    $keep->route = $menu->route;
                }
                if (empty($keep->icon) && !empty($menu->icon)) {
                    $keep->icon = $menu->icon;
                }
                if (empty($keep->permission_name) && !empty($menu->permission_name)) {
                    $keep->permission_name = $menu->permission_name;
                }
                $keep->save();
            }

            Menu::query()
                ->where('parent_id', $menu->id)
                ->update(['parent_id' => $existingKeepId]);

            $duplicateIds[] = (int) $menu->id;
        }

        if ($duplicateIds !== []) {
            Menu::query()->whereIn('id', array_values(array_unique($duplicateIds)))->delete();
        }
    }
}
