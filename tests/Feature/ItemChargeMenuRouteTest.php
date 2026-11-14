<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ItemChargeMenuRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_list_menu_uses_pathology_catalog_route(): void
    {
        $parent = Menu::query()->create([
            'name' => 'Item Charge',
            'route' => 'backend.Itemcharge.index',
            'permission_name' => 'item-charge',
            'status' => 'Active',
        ]);
        $itemList = Menu::query()->create([
            'name' => 'Item List',
            'route' => 'backend.Itemcharge.index',
            'permission_name' => 'itemcharge-list',
            'parent_id' => $parent->id,
            'status' => 'Active',
        ]);

        $migration = require database_path(
            'migrations/2026_11_13_000002_fix_item_charge_menu_routes.php'
        );
        $migration->up();

        $this->assertNull($parent->fresh()->route);
        $this->assertSame('backend.itemcharge.index', $itemList->fresh()->route);
        $this->assertSame('itemcharge-list', $itemList->fresh()->permission_name);
    }

    public function test_item_charge_menu_seeder_preserves_the_item_list_route(): void
    {
        $parent = Menu::query()->create([
            'name' => 'Item Charge',
            'route' => 'backend.Itemcharge.index',
            'permission_name' => 'item-charge',
            'status' => 'Active',
        ]);
        $itemList = Menu::query()->create([
            'name' => 'Item List',
            'route' => 'backend.Itemcharge.index',
            'permission_name' => 'itemcharge-list',
            'parent_id' => $parent->id,
            'status' => 'Active',
        ]);

        $this->seed(\Database\Seeders\UpdateItemChargeMenuSeeder::class);

        $this->assertNull($parent->fresh()->route);
        $this->assertSame('backend.itemcharge.index', $itemList->fresh()->route);
        $this->assertSame('itemcharge-list', $itemList->fresh()->permission_name);
    }

    public function test_legacy_uppercase_itemcharge_url_redirects_to_the_item_list_page(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        $admin = Admin::query()->create([
            'first_name' => 'Item',
            'last_name' => 'List',
            'email' => 'item-list-developer@example.test',
            'phone' => '01700000001',
            'password' => bcrypt('password'),
            'status' => 'Active',
            'role_id' => $developerRole->id,
        ]);
        $admin->assignRole($developerRole);
        $this->actingAs($admin, 'admin');

        $this->get('/Itemcharge')
            ->assertRedirect(route('backend.itemcharge.index'));

        $this->get(route('backend.itemcharge.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Backend/PathologyTest/Index')
                ->where('pageTitle', 'Item List'));
    }
}
