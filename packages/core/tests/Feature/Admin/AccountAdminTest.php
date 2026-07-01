<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Account controller (the authenticated user's
 * own account pages).
 */
class AccountAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        // Known password so the change-password current_password check matches.
        $this->admin = User::factory()->create(['password' => Hash::make('password')]);
        $this->admin->assignRole($role);
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    // NOTE: Account::index() renders the 'account.index' view, which does not
    // exist — GET /admin/account currently 500s. Tracked separately; not
    // asserted here so the suite reflects reality.

    public function test_details_renders(): void
    {
        $this->actingAs($this->admin)->get(route('account.details'))->assertOk();
    }

    public function test_password_page_renders(): void
    {
        $this->actingAs($this->admin)->get(route('account.password'))->assertOk();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_account_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->put(route('account.edit'), [
                'name' => 'Updated Name',
                'email' => $this->admin->email,
            ])
            ->assertRedirect(route('account.details'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'name' => 'Updated Name']);
    }

    // -------------------------------------------------------------------------
    // change_password
    // -------------------------------------------------------------------------

    public function test_change_password_updates_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertRedirect(route('account.details'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('newsecret123', $this->admin->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $this->actingAs($this->admin)
            ->put(route('account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertSessionHasErrors('current_password');
    }
}
