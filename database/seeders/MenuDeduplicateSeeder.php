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
            $menus = Menu::query()
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            $keepByKey = [];
            $duplicateIds = [];

            foreach ($menus as $menu) {
                $parentId = $menu->parent_id ?? 0;
                $permission = trim((string) ($menu->permission_name ?? ''));

                // Prefer permission-based uniqueness; fallback for empty permission names.
                $key = $permission !== ''
                    ? sprintf('p:%s:%s', $parentId, $permission)
                    : sprintf('n:%s:%s:%s', $parentId, trim((string) $menu->name), trim((string) $menu->route));

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
