<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Laravel\Sanctum\Sanctum;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_employee_via_api()
    {
        Role::create(['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        Sanctum::actingAs($user, ['*']);

        // Create
        $payload = [
            'employee_id' => 'EMP100',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '12345',
            'status' => 'active',
        ];

        $res = $this->postJson('/api/v1/employees', $payload);
        $res->assertStatus(201);

        $this->assertDatabaseHas('employees', ['employee_id' => 'EMP100']);

        $id = $res->json('id');

        // Show
        $this->getJson('/api/v1/employees/' . $id)->assertStatus(200)->assertJsonFragment(['employee_id' => 'EMP100']);

        // Update
        $this->putJson('/api/v1/employees/' . $id, ['first_name' => 'Jane'])->assertStatus(200)->assertJsonFragment(['first_name' => 'Jane']);

        $this->assertDatabaseHas('employees', ['id' => $id, 'first_name' => 'Jane']);

        // Delete
        $this->deleteJson('/api/v1/employees/' . $id)->assertStatus(204);
        $this->assertDatabaseMissing('employees', ['id' => $id]);
    }
}
