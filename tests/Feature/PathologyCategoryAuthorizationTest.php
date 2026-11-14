<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\TestCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PathologyCategoryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_create_and_edit_permissions_can_create_and_edit_item_categories(): void
    {
        $user = $this->createUserWithPermissions([
            'test-category-list',
            'test-category-list-create',
            'test-category-list-edit',
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('backend.pathologycategory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canCreateCategory', true));

        $createResponse = $this->post(route('backend.pathologycategory.store'), ['name' => 'Hormone']);
        $this->assertFalse(
            $createResponse->getSession()->has('errorMessage'),
            json_encode(\App\Models\SystemErrorLog::query()->latest('id')->first()?->toArray())
        );

        $category = TestCategory::query()->where('name', 'Hormone')->firstOrFail();

        $updateResponse = $this->put(
            route('backend.pathologycategory.update', $category->id),
            ['name' => 'Hormone Updated']
        );
        $updateResponse->assertRedirect();

        $this->assertDatabaseHas('testcategories', [
            'id' => $category->id,
            'name' => 'Hormone Updated',
        ]);
    }

    public function test_user_without_create_or_edit_permissions_cannot_access_those_actions(): void
    {
        $user = $this->createUserWithPermissions(['test-category-list']);
        $category = TestCategory::query()->create(['name' => 'Hormone', 'status' => 'Active']);

        $this->actingAs($user, 'admin')
            ->get(route('backend.pathologycategory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canCreateCategory', false));

        $this->get(route('backend.pathologycategory.create'))->assertForbidden();
        $this->post(route('backend.pathologycategory.store'), ['name' => 'Unauthorized'])->assertForbidden();
        $this->get(route('backend.pathologycategory.edit', $category->id))->assertForbidden();
        $this->put(route('backend.pathologycategory.update', $category->id), ['name' => 'Unauthorized'])
            ->assertForbidden();
    }

    public function test_only_developer_can_delete_item_categories_even_if_another_user_has_delete_permission(): void
    {
        $user = $this->createUserWithPermissions(['test-category-list', 'test-category-list-delete']);
        $category = TestCategory::query()->create(['name' => 'Protected Category', 'status' => 'Active']);

        $this->actingAs($user, 'admin')
            ->delete(route('backend.pathologycategory.destroy', $category->id))
            ->assertForbidden();

        $this->assertDatabaseHas('testcategories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }

    public function test_developer_can_create_edit_and_delete_without_category_permissions(): void
    {
        $developer = $this->createUserWithPermissions([], 'developer');

        $this->actingAs($developer, 'admin')
            ->get(route('backend.pathologycategory.create'))
            ->assertOk();

        $createResponse = $this->post(route('backend.pathologycategory.store'), ['name' => 'Hormone']);
        $this->assertFalse(
            $createResponse->getSession()->has('errorMessage'),
            json_encode(\App\Models\SystemErrorLog::query()->latest('id')->first()?->toArray())
        );
        $category = TestCategory::query()->where('name', 'Hormone')->firstOrFail();

        $this->get(route('backend.pathologycategory.edit', $category->id))->assertOk();
        $updateResponse = $this->put(
            route('backend.pathologycategory.update', $category->id),
            ['name' => 'Hormone Updated']
        );
        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('testcategories', ['id' => $category->id, 'name' => 'Hormone Updated']);

        $this->delete(route('backend.pathologycategory.destroy', $category->id))->assertRedirect();
        $this->assertDatabaseMissing('testcategories', ['id' => $category->id, 'deleted_at' => null]);
    }

    private function createUserWithPermissions(array $permissions, string $roleName = 'item-category-user'): Admin
    {
        Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);

        $role = Role::query()->create([
            'name' => $roleName,
            'guard_name' => 'admin',
            'is_private' => $roleName === 'developer',
        ]);

        if ($permissions !== []) {
            $role->givePermissionTo(collect($permissions)->map(fn ($name) => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'admin',
            ]))->all());
        }

        $user = Admin::query()->create([
            'first_name' => 'Category',
            'last_name' => 'User',
            'email' => $roleName . '@example.test',
            'phone' => '01710000999',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
