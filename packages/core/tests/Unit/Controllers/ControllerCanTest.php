<?php

namespace Tests\Unit\Controllers;

use AdAstra\Http\Controllers\Controller;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControllerCanTest extends TestCase
{
    use RefreshDatabase;

    private Controller $controller;

    public function test_returns_true_when_authenticated_user_has_permission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'edit posts', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->actingAs($user);

        $this->assertTrue($this->controller->check('edit posts'));
    }

    // -------------------------------------------------------------------------
    // can()
    // -------------------------------------------------------------------------

    public function test_returns_false_when_authenticated_user_lacks_permission(): void
    {
        Permission::firstOrCreate(['name' => 'edit posts', 'guard_name' => 'web']);
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse($this->controller->check('edit posts'));
    }

    public function test_returns_false_for_unauthenticated_caller(): void
    {
        // No actingAs — Gate::allows() must not throw on a null user
        $this->assertFalse($this->controller->check('edit posts'));
    }

    public function test_returns_true_when_gate_allows_via_closure(): void
    {
        $user = User::factory()->create();
        Gate::define('always-true', fn() => true);

        $this->actingAs($user);

        $this->assertTrue($this->controller->check('always-true'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class extends Controller {
            public function check(string $permission): bool
            {
                return $this->can($permission);
            }
        };
    }
}
