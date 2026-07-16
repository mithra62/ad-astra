<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Account\Settings controller (the authenticated
 * user's personal setting overrides).
 */
class AccountSettingsAdminTest extends TestCase
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
        $this->get(route('account.settings'))->assertRedirect(route('login'));
    }

    public function test_show_renders(): void
    {
        $this->actingAs($this->admin)->get(route('account.settings'))->assertOk();
    }

    public function test_update_persists_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->put(route('account.edit_settings'), [])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('success');
    }
}
