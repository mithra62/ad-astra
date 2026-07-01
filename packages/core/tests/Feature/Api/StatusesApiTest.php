<?php

namespace Tests\Feature\Api;

use AdAstra\Models\Role;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature coverage for the Statuses API controller (flat resource, optionally
 * filtered by status_group_id).
 *
 * Gates: read/write require "read statuses"; store/update authorize() require
 * "create status" / "edit status"; destroy aborts 403 without "delete status".
 */
class StatusesApiTest extends TestCase
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
        foreach (['read statuses', 'create status', 'edit status', 'delete status'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        return User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_index_rejects_guests_with_401(): void
    {
        $this->getJson('/api/v1/statuses')->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_statuses_for_super_admin(): void
    {
        $status = Status::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/statuses')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.id', $status->id);
    }

    public function test_index_filters_by_status_group_id(): void
    {
        $groupA = StatusGroup::factory()->create();
        $groupB = StatusGroup::factory()->create();
        $mine = Status::factory()->create(['status_group_id' => $groupA->id]);
        $theirs = Status::factory()->create(['status_group_id' => $groupB->id]);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $response = $this->getJson("/api/v1/statuses?status_group_id={$groupA->id}")->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_index_denies_user_without_read_permission_with_404(): void
    {
        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson('/api/v1/statuses')->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_the_status_for_super_admin(): void
    {
        $status = Status::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/statuses/{$status->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $status->id);
    }

    public function test_show_returns_404_for_missing_status(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/statuses/999999')->assertNotFound();
    }

    public function test_show_denies_user_without_read_permission_with_404(): void
    {
        $status = Status::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/statuses/{$status->id}")->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_a_status_for_super_admin(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/statuses', [
            'status_group_id' => $group->id,
            'name' => 'Draft',
            'handle' => 'draft',
            'sort_order' => 1,
        ])->assertCreated()->assertJsonPath('data.name', 'Draft');

        $this->assertDatabaseHas('statuses', [
            'status_group_id' => $group->id,
            'handle' => 'draft',
        ]);
    }

    public function test_store_rejects_duplicate_handle_within_group(): void
    {
        $group = StatusGroup::factory()->create();
        Status::factory()->create(['status_group_id' => $group->id, 'handle' => 'draft']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/statuses', [
            'status_group_id' => $group->id,
            'name' => 'Draft Two',
            'handle' => 'draft',
            'sort_order' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('handle');
    }

    public function test_store_requires_name_handle_and_sort_order(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/statuses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'handle', 'sort_order']);
    }

    public function test_store_denies_user_without_create_permission_with_403(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->postJson('/api/v1/statuses', [
            'status_group_id' => $group->id,
            'name' => 'Draft',
            'handle' => 'draft',
            'sort_order' => 0,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_the_status_for_super_admin(): void
    {
        $group = StatusGroup::factory()->create();
        $status = Status::factory()->create([
            'status_group_id' => $group->id,
            'name' => 'Old',
            'handle' => 'old',
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/statuses/{$status->id}", [
            'name' => 'Published',
            'handle' => 'old',
            'sort_order' => 0,
        ])->assertOk()->assertJsonPath('data.name', 'Published');

        $this->assertDatabaseHas('statuses', ['id' => $status->id, 'name' => 'Published']);
    }

    public function test_update_returns_404_for_missing_status(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson('/api/v1/statuses/999999', [
            'name' => 'Nope',
            'handle' => 'nope',
            'sort_order' => 0,
        ])->assertNotFound();
    }

    public function test_update_denies_user_without_edit_permission_with_403(): void
    {
        $status = Status::factory()->create(['handle' => 'edit-me']);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->putJson("/api/v1/statuses/{$status->id}", [
            'name' => 'Nope',
            'handle' => 'edit-me',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_the_status_for_super_admin(): void
    {
        $status = Status::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/statuses/{$status->id}")->assertNoContent();

        $this->assertDatabaseMissing('statuses', ['id' => $status->id]);
    }

    public function test_destroy_returns_404_for_missing_status(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson('/api/v1/statuses/999999')->assertNotFound();
    }

    public function test_destroy_denies_user_without_delete_permission_with_403(): void
    {
        $status = Status::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->deleteJson("/api/v1/statuses/{$status->id}")->assertForbidden();

        $this->assertDatabaseHas('statuses', ['id' => $status->id]);
    }
}
