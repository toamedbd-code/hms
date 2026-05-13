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
            $normalizeText = static function ($value): string {
                return strtolower(trim((string) $value));
            };

            $normalizeRoute = static function ($route) use ($normalizeText): string {
                $route = $normalizeText($route);

                if ($route === '') {
                    return '';
                }

                $aliases = [
                    'backend.pharmacy.supplier.payment' => 'backend.supplierpayment.index',
                    'admin.attendance.devices' => 'backend.attendance.devices',
                ];

                return $aliases[$route] ?? $route;
            };

            $menus = Menu::query()
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            $keepByKey = [];
            $duplicateIds = [];

            foreach ($menus as $menu) {
                $parentId = $menu->parent_id ?? 0;
                $name = $normalizeText($menu->name ?? '');
                $permission = $normalizeText($menu->permission_name ?? '');
                $route = $normalizeRoute($menu->route ?? '');

                // Keep DB rows on canonical route names so runtime filtering stays predictable.
                if ($route !== '' && trim((string) ($menu->route ?? '')) !== $route) {
                    $menu->route = $route;
                    $menu->save();
                }

                // Global dedupe strategy:
                // 1) Route-based (after route alias normalization)
                // 2) Name + permission fallback for non-route menus
                // 3) Name-only fallback when permission is empty
                if ($route !== '') {
                    $key = sprintf('r:%s:%s', $parentId, $route);
                } elseif ($name !== '' && $permission !== '') {
                    $key = sprintf('np:%s:%s:%s', $parentId, $name, $permission);
                } elseif ($name !== '') {
                    $key = sprintf('n:%s:%s', $parentId, $name);
                } elseif ($permission !== '') {
                    $key = sprintf('p:%s:%s', $parentId, $permission);
                } else {
                    // No stable identity available; keep row as-is.
                    continue;
                }

                if (!isset($keepByKey[$key])) {
                    $keepByKey[$key] = (int) $menu->id;
                    continue;
                }

                $keepId = $keepByKey[$key];
                $duplicateId = (int) $menu->id;

                // Re-parent children from duplicate menu to the kept menu before deleting.
                Menu::query()
                    ->where('parent_id', $duplicateId)
                    ->update(['parent_id' => $keepId]);

                $duplicateIds[] = $duplicateId;
            }

            if (!empty($duplicateIds)) {
                Menu::query()->whereIn('id', $duplicateIds)->delete();
            }
        });
    }
}
