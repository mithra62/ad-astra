<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\User\Layout controller. Only show() is routed
 * (GET users/layouts); it 404s when no user field layout is configured, which
 * is the default state in a fresh install.
 */
class UserLayoutAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_show_redirects_guests_to_login(): void
    {
        $this->get(route('users.layouts.show'))->assertRedirect(route('login'));
    }

    public function test_show_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.layouts.show'))
            ->assertForbidden();
    }

    public function test_show_returns_404_when_no_layout_configured(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.layouts.show'))
            ->assertNotFound();
    }
}
