<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_seeder_keeps_same_route_under_different_parent_menus(): void
    {
        $parentA = Menu::query()->create([
            'name' => 'Parent A',
            'icon' => 'list',
            'route' => null,
            'permission_name' => 'parent-a',
            'status' => 'Active',
            'sorting' => 1,
        ]);

        $parentB = Menu::query()->create([
            'name' => 'Parent B',
            'icon' => 'list',
            'route' => null,
            'permission_name' => 'parent-b',
            'status' => 'Active',
            'sorting' => 2,
        ]);

        Menu::query()->create([
            'parent_id' => $parentA->id,
            'name' => 'Item List A',
            'icon' => 'list',
            'route' => 'backend.itemcharge.index',
            'permission_name' => 'itemcharge-list',
            'status' => 'Active',
            'sorting' => 1,
        ]);

        Menu::query()->create([
            'parent_id' => $parentB->id,
            'name' => 'Item List B',
            'icon' => 'list',
            'route' => 'backend.itemcharge.index',
            'permission_name' => 'itemcharge-list',
            'status' => 'Active',
            'sorting' => 1,
        ]);

        $this->seed(\Database\Seeders\MenuSeeder::class);

        $itemChargeMenus = Menu::query()
            ->where('route', 'backend.itemcharge.index')
            ->get();

        $this->assertGreaterThanOrEqual(2, $itemChargeMenus->count());
        $this->assertGreaterThan(1, $itemChargeMenus->pluck('parent_id')->unique()->count());
        $this->assertSame(
            $itemChargeMenus->count(),
            $itemChargeMenus->pluck(fn (Menu $menu) => $menu->parent_id . ':' . $menu->name)->unique()->count()
        );
    }
}
