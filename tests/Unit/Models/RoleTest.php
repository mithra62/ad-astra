<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_delete_returns_false_for_id_1(): void
    {
        $role = new Role;
        $role->id = 1;

        $this->assertFalse($role->canDelete());
    }

    public function test_can_delete_returns_false_for_id_2(): void
    {
        $role = new Role;
        $role->id = 2;

        $this->assertFalse($role->canDelete());
    }

    public function test_can_delete_returns_false_for_id_3(): void
    {
        $role = new Role;
        $role->id = 3;

        $this->assertFalse($role->canDelete());
    }

    public function test_can_delete_returns_true_for_id_4(): void
    {
        $role = new Role;
        $role->id = 4;

        $this->assertTrue($role->canDelete());
    }

    public function test_can_delete_returns_true_for_any_non_locked_id(): void
    {
        foreach ([5, 10, 100, 999] as $id) {
            $role = new Role;
            $role->id = $id;

            $this->assertTrue($role->canDelete(), "Expected canDelete to return true for id={$id}");
        }
    }

    public function test_locked_ids_are_1_2_and_3(): void
    {
        $role = Role::factory()->create(); // id=1
        $this->assertFalse($role->fresh()->canDelete());

        $role2 = Role::factory()->create(); // id=2
        $this->assertFalse($role2->fresh()->canDelete());

        $role3 = Role::factory()->create(); // id=3
        $this->assertFalse($role3->fresh()->canDelete());

        $role4 = Role::factory()->create(); // id=4
        $this->assertTrue($role4->fresh()->canDelete());
    }
}
