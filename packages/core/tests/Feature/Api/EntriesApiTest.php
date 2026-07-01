<?php

namespace Tests\Feature\Api;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\Role;
use AdAstra\Models\Status;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature coverage for the Entries API controller (nested under entry-groups).
 *
 * Auth model under test:
 *   - auth:sanctum guards every route (guests get 401).
 *   - A "super admin" role passes every gate via the Gate::before bypass
 *     registered in AppServiceProvider.
 *   - A plain authenticated user, lacking the relevant permission, is denied:
 *     read/write gates abort 404, destroy aborts 403, and the Store/Edit
 *     FormRequest authorize() checks abort 403.
 *   - Ownership is scoped: an entry that belongs to a different group is 404.
 */
class EntriesApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * An authenticated user with none of the entry permissions. The permission
     * rows are created (but not granted) so Gate checks resolve to false rather
     * than throwing PermissionDoesNotExist.
     */
    private function plainUser(): User
    {
        foreach (['read entries', 'create entry', 'edit entry', 'delete entry'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        return User::factory()->create();
    }

    private function group(): EntryGroup
    {
        $group = EntryGroup::factory()->create();

        // A real group's StatusGroup always has a default status; entry creation
        // falls back to it when no status is supplied (EntryRepository).
        Status::factory()->default()->create([
            'status_group_id' => $group->status_group_id,
            'handle' => 'draft',
        ]);

        return $group;
    }

    private function typeFor(EntryGroup $group, string $handle = 'article'): EntryType
    {
        return EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle' => $handle,
        ]);
    }

    private function entryIn(EntryGroup $group, array $attributes = []): Entry
    {
        $type = $this->typeFor($group, 'article-' . fake()->unique()->numberBetween(1, 999999));

        return Entry::factory()->create(array_merge([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // Authentication (guests)
    // -------------------------------------------------------------------------

    public function test_index_rejects_guests_with_401(): void
    {
        $group = $this->group();

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries")
            ->assertUnauthorized();
    }

    public function test_show_rejects_guests_with_401(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}")
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_entries_in_the_group_for_super_admin(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries")
            ->assertOk()
            ->assertJsonPath('data.0.id', $entry->id)
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_lists_entries_from_the_requested_group(): void
    {
        $group = $this->group();
        $other = $this->group();
        $mine = $this->entryIn($group);
        $theirs = $this->entryIn($other);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $response = $this->getJson("/api/v1/entry-groups/{$group->id}/entries")->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_index_denies_user_without_read_permission_with_404(): void
    {
        $group = $this->group();
        $this->entryIn($group);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries")
            ->assertNotFound();
    }

    public function test_index_caps_limit_at_100(): void
    {
        $group = $this->group();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries?limit=500")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_the_entry_for_super_admin(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id);
    }

    public function test_show_returns_404_when_entry_belongs_to_a_different_group(): void
    {
        $group = $this->group();
        $other = $this->group();
        $entry = $this->entryIn($other);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        // Entry exists, but not within {group} — must not leak across groups.
        $this->getJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}")
            ->assertNotFound();
    }

    public function test_show_returns_404_for_missing_entry(): void
    {
        $group = $this->group();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries/999999")
            ->assertNotFound();
    }

    public function test_show_denies_user_without_read_permission_with_404(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_an_entry_for_super_admin(): void
    {
        $group = $this->group();
        $this->typeFor($group, 'article');

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $response = $this->postJson("/api/v1/entry-groups/{$group->id}/entries", [
            'type_handle' => 'article',
            'title' => 'Hello World',
            'handle' => 'hello-world',
        ])->assertCreated();

        $this->assertDatabaseHas('entries', [
            'entry_group_id' => $group->id,
            'handle' => 'hello-world',
            'title' => 'Hello World',
        ]);
        $response->assertJsonPath('data.title', 'Hello World');
    }

    public function test_store_rejects_type_handle_from_another_group_with_422(): void
    {
        $group = $this->group();
        $other = $this->group();
        $this->typeFor($other, 'foreign-type');

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson("/api/v1/entry-groups/{$group->id}/entries", [
            'type_handle' => 'foreign-type',
            'title' => 'Nope',
            'handle' => 'nope',
        ])->assertStatus(422)->assertJsonValidationErrors('type_handle');
    }

    public function test_store_requires_title_and_handle(): void
    {
        $group = $this->group();
        $this->typeFor($group, 'article');

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson("/api/v1/entry-groups/{$group->id}/entries", [
            'type_handle' => 'article',
        ])->assertStatus(422)->assertJsonValidationErrors(['title', 'handle']);
    }

    public function test_store_rejects_duplicate_handle_within_group(): void
    {
        $group = $this->group();
        $this->typeFor($group, 'article');
        $this->entryIn($group, ['handle' => 'taken']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson("/api/v1/entry-groups/{$group->id}/entries", [
            'type_handle' => 'article',
            'title' => 'Dupe',
            'handle' => 'taken',
        ])->assertStatus(422)->assertJsonValidationErrors('handle');
    }

    public function test_store_denies_user_without_create_permission_with_403(): void
    {
        $group = $this->group();
        $this->typeFor($group, 'article');

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->postJson("/api/v1/entry-groups/{$group->id}/entries", [
            'type_handle' => 'article',
            'title' => 'Hello',
            'handle' => 'hello',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_the_entry_for_super_admin(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group, ['title' => 'Old', 'handle' => 'old']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}", [
            'title' => 'New Title',
            'handle' => 'old',
        ])->assertOk()->assertJsonPath('data.title', 'New Title');

        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'title' => 'New Title']);
    }

    public function test_update_returns_404_when_entry_belongs_to_a_different_group(): void
    {
        $group = $this->group();
        $other = $this->group();
        $entry = $this->entryIn($other, ['handle' => 'foreign']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}", [
            'title' => 'Hijack',
            'handle' => 'foreign',
        ])->assertNotFound();
    }

    public function test_update_denies_user_without_edit_permission_with_403(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group, ['handle' => 'edit-me']);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->putJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}", [
            'title' => 'Nope',
            'handle' => 'edit-me',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_the_entry_for_super_admin(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    public function test_destroy_returns_404_when_entry_belongs_to_a_different_group(): void
    {
        $group = $this->group();
        $other = $this->group();
        $entry = $this->entryIn($other);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('entries', ['id' => $entry->id]);
    }

    public function test_destroy_denies_user_without_delete_permission_with_403(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->deleteJson("/api/v1/entry-groups/{$group->id}/entries/{$entry->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('entries', ['id' => $entry->id]);
    }
}
