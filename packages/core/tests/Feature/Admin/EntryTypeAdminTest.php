<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\EntryType;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Entry\Type controller (top-level entry types).
 *
 * destroy redirects back with a "failure" flash when the type does not exist.
 */
class EntryTypeAdminTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $this->get(route('entries.types'))->assertRedirect(route('login'));
    }

    public function test_index_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('entries.types'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_index_renders(): void
    {
        EntryType::factory()->count(2)->create();

        $this->actingAs($this->admin)->get(route('entries.types'))->assertOk();
    }

    public function test_create_renders(): void
    {
        $this->actingAs($this->admin)->get(route('entries.types.create'))->assertOk();
    }

    public function test_edit_renders(): void
    {
        $type = EntryType::factory()->create();

        $this->actingAs($this->admin)->get(route('entries.types.edit', $type->id))->assertOk();
    }

    public function test_edit_returns_404_for_missing_type(): void
    {
        $this->actingAs($this->admin)->get(route('entries.types.edit', 999999))->assertNotFound();
    }

    public function test_confirm_renders(): void
    {
        $type = EntryType::factory()->create();

        $this->actingAs($this->admin)->get(route('entries.types.confirm', $type->id))->assertOk();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_type_and_redirects_to_edit(): void
    {
        $this->actingAs($this->admin)
            ->post(route('entries.types.store'), [
                'name' => 'Blog Post',
                'handle' => 'blog-post',
            ])
            ->assertSessionHas('success')
            ->assertRedirectContains('/admin/entries/types/');

        $this->assertDatabaseHas('entry_types', ['handle' => 'blog-post']);
    }

    public function test_store_requires_name_and_handle(): void
    {
        $this->actingAs($this->admin)
            ->post(route('entries.types.store'), [])
            ->assertSessionHasErrors(['name', 'handle']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_type_and_redirects(): void
    {
        $type = EntryType::factory()->create(['name' => 'Old', 'handle' => 'old']);

        $this->actingAs($this->admin)
            ->put(route('entries.types.update', $type->id), [
                'name' => 'New Name',
                'handle' => 'old',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entry_types', ['id' => $type->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_type(): void
    {
        $this->actingAs($this->admin)
            ->put(route('entries.types.update', 999999), ['name' => 'Nope', 'handle' => 'nope'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_type_and_redirects(): void
    {
        $type = EntryType::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('entries.types.destroy', $type->id), ['confirm_removal' => 1])
            ->assertRedirect(route('entries.types'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('entry_types', ['id' => $type->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $type = EntryType::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('entries.types.destroy', $type->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('entry_types', ['id' => $type->id]);
    }

    public function test_destroy_missing_type_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('entries.types.destroy', 999999), ['confirm_removal' => 1])
            ->assertRedirect(route('entries.types'))
            ->assertSessionHas('failure');
    }
}
