<?php

namespace Tests\Feature\Api;

use AdAstra\Models\Role;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature coverage for the User API controller (apiResource).
 *
 * Gate quirks encoded faithfully: index requires "read user" (singular) while
 * show requires "read users" (plural); store/update authorize() require
 * "create user" / "edit user"; destroy aborts 403 without "delete user" and
 * also blocks self-deletion (403) even for a super admin.
 */
class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function plainUser(): User
    {
        foreach (['read user', 'read users', 'create user', 'edit user', 'delete user'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        return User::factory()->create();
    }

    private function assignableRole(): Role
    {
        return Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_index_rejects_guests_with_401(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // index / show
    // -------------------------------------------------------------------------

    public function test_index_lists_users_for_super_admin(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_denies_user_without_read_user_permission_with_404(): void
    {
        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson('/api/v1/users')->assertNotFound();
    }

    public function test_show_returns_the_user_for_super_admin(): void
    {
        $target = User::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);
    }

    public function test_show_returns_404_for_missing_user(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/users/999999')->assertNotFound();
    }

    public function test_show_denies_user_without_read_users_permission_with_404(): void
    {
        $target = User::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/users/{$target->id}")->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_a_user_for_super_admin(): void
    {
        $this->assignableRole();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/users', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'roles' => ['editor'],
        ])->assertCreated()->assertJsonPath('data.email', 'jane@example.com');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_store_requires_core_fields(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/users', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'roles']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $this->assignableRole();
        User::factory()->create(['email' => 'taken@example.com']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/users', [
            'name' => 'Dupe',
            'email' => 'taken@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'roles' => ['editor'],
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_store_denies_user_without_create_permission_with_403(): void
    {
        $this->assignableRole();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->postJson('/api/v1/users', [
            'name' => 'Jane',
            'email' => 'jane2@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'roles' => ['editor'],
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_the_user_for_super_admin(): void
    {
        $this->assignableRole();
        $target = User::factory()->create(['name' => 'Old Name']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/users/{$target->id}", [
            'name' => 'New Name',
            'email' => $target->email,
            'roles' => ['editor'],
        ])->assertOk()->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_user(): void
    {
        $this->assignableRole();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson('/api/v1/users/999999', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'roles' => ['editor'],
        ])->assertNotFound();
    }

    public function test_update_denies_user_without_edit_permission_with_403(): void
    {
        $this->assignableRole();
        $target = User::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->putJson("/api/v1/users/{$target->id}", [
            'name' => 'Nope',
            'email' => $target->email,
            'roles' => ['editor'],
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_another_user_for_super_admin(): void
    {
        $target = User::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/users/{$target->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_destroy_blocks_self_deletion_even_for_super_admin(): void
    {
        $admin = $this->superAdmin();

        Sanctum::actingAs($admin, ['*']);

        $this->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_destroy_returns_404_for_missing_user(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson('/api/v1/users/999999')->assertNotFound();
    }

    public function test_destroy_denies_user_without_delete_permission_with_403(): void
    {
        $target = User::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->deleteJson("/api/v1/users/{$target->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
