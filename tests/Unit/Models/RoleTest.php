<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_has_locked_attribute(): void
    {
        $role = new Role();
        $reflection = new \ReflectionProperty(Role::class, 'locked');
        $reflection->setAccessible(true);

        $this->assertEquals([1, 2, 3], $reflection->getValue($role));
    }

    public function test_can_delete_returns_false_for_locked_roles(): void
    {
        foreach ([1, 2, 3] as $id) {
            $role = new Role();
            $role->id = $id;
            $this->assertFalse($role->canDelete(), "Role with ID {$id} should not be deletable.");
        }
    }

    public function test_can_delete_returns_true_for_unlocked_roles(): void
    {
        $role = new Role();
        $role->id = 4;
        $this->assertTrue($role->canDelete());

        $role->id = 99;
        $this->assertTrue($role->canDelete());
    }

    public function test_role_can_be_created_via_factory(): void
    {
        $role = Role::factory()->create(['name' => 'test-role']);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'test-role'
        ]);
    }
}
