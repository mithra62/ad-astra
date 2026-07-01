<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Field\Group controller (field groups).
 */
class FieldGroupAdminTest extends TestCase
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
        $this->get(route('fields.groups'))->assertRedirect(route('login'));
    }

    public function test_index_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('fields.groups'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_index_renders(): void
    {
        FieldGroup::factory()->count(2)->create();

        $this->actingAs($this->admin)->get(route('fields.groups'))->assertOk();
    }

    public function test_create_renders(): void
    {
        $this->actingAs($this->admin)->get(route('fields.groups.create'))->assertOk();
    }

    public function test_show_renders(): void
    {
        $group = FieldGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('fields.groups.show', $group->id))->assertOk();
    }

    public function test_show_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)->get(route('fields.groups.show', 999999))->assertNotFound();
    }

    public function test_edit_renders(): void
    {
        $group = FieldGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('fields.groups.edit', $group->id))->assertOk();
    }

    public function test_confirm_renders(): void
    {
        $group = FieldGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('fields.groups.confirm', $group->id))->assertOk();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_group_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->post(route('fields.groups.store'), [
                'name' => 'SEO Fields',
                'handle' => 'seo-fields',
            ])
            ->assertSessionHas('success')
            ->assertRedirectContains('/admin/fields/groups/');

        $this->assertDatabaseHas('field_groups', ['handle' => 'seo-fields']);
    }

    public function test_store_requires_name_and_handle(): void
    {
        $this->actingAs($this->admin)
            ->post(route('fields.groups.store'), [])
            ->assertSessionHasErrors(['name', 'handle']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_group_and_redirects(): void
    {
        $group = FieldGroup::factory()->create(['name' => 'Old']);

        $this->actingAs($this->admin)
            ->put(route('fields.groups.update', $group->id), [
                'name' => 'New Name',
                'handle' => $group->handle,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)
            ->put(route('fields.groups.update', 999999), ['name' => 'Nope', 'handle' => 'nope'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_group_and_redirects(): void
    {
        $group = FieldGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('fields.groups.destroy', $group->id), ['confirm_removal' => 1])
            ->assertRedirect(route('fields.groups'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('field_groups', ['id' => $group->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $group = FieldGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('fields.groups.destroy', $group->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('field_groups', ['id' => $group->id]);
    }

    public function test_destroy_missing_group_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('fields.groups.destroy', 999999), ['confirm_removal' => 1])
            ->assertRedirect(route('fields.groups'))
            ->assertSessionHas('failure');
    }
}
