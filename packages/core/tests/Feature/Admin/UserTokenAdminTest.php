<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\User\Token controller (an admin managing
 * another user's API tokens).
 */
class UserTokenAdminTest extends TestCase
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

    /** A target user who holds the "api" permission (required for the create screen). */
    private function apiUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'api', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function tokenIdFor(User $user): int
    {
        return $user->createToken('Test Token')->accessToken->id;
    }

    // -------------------------------------------------------------------------
    // index / create
    // -------------------------------------------------------------------------

    public function test_index_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get(route('users.token.index', $user->id))->assertOk();
    }

    public function test_index_missing_user_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.token.index', 999999))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('failure');
    }

    public function test_create_renders_for_api_user(): void
    {
        $user = $this->apiUser();

        $this->actingAs($this->admin)->get(route('users.token.create', $user->id))->assertOk();
    }

    public function test_create_redirects_with_failure_when_user_lacks_api_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('users.token.create', $user->id))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('failure');
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_token_and_redirects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('users.token.store', $user->id), ['name' => 'CI Token'])
            ->assertRedirect(route('users.edit', $user->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'CI Token',
        ]);
    }

    public function test_store_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('users.token.store', $user->id), [])
            ->assertSessionHasErrors('name');
    }

    // -------------------------------------------------------------------------
    // edit / update
    // -------------------------------------------------------------------------

    public function test_edit_renders(): void
    {
        $user = User::factory()->create();
        $id = $this->tokenIdFor($user);

        $this->actingAs($this->admin)->get(route('users.token.edit', [$user->id, $id]))->assertOk();
    }

    public function test_edit_returns_404_for_missing_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get(route('users.token.edit', [$user->id, 999999]))->assertNotFound();
    }

    public function test_update_renames_token_and_redirects(): void
    {
        $user = User::factory()->create();
        $id = $this->tokenIdFor($user);

        $this->actingAs($this->admin)
            ->put(route('users.token.update', [$user->id, $id]), ['name' => 'Renamed Token'])
            ->assertRedirect(route('users.edit', $user->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $id, 'name' => 'Renamed Token']);
    }

    // -------------------------------------------------------------------------
    // confirm / destroy
    // -------------------------------------------------------------------------

    public function test_confirm_renders(): void
    {
        $user = User::factory()->create();
        $id = $this->tokenIdFor($user);

        $this->actingAs($this->admin)->get(route('users.token.confirm', [$user->id, $id]))->assertOk();
    }

    public function test_destroy_deletes_token_and_redirects(): void
    {
        $user = User::factory()->create();
        $id = $this->tokenIdFor($user);

        $this->actingAs($this->admin)
            ->delete(route('users.token.destroy', [$user->id, $id]), ['confirm_removal' => 1])
            ->assertRedirect(route('users.edit', $user->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $user = User::factory()->create();
        $id = $this->tokenIdFor($user);

        $this->actingAs($this->admin)
            ->delete(route('users.token.destroy', [$user->id, $id]), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $id]);
    }
}
