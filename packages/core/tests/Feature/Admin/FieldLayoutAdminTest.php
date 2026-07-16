<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\FieldLayout;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\FieldLayout controller.
 */
class FieldLayoutAdminTest extends TestCase
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
        $this->get(route('field-layouts'))->assertRedirect(route('login'));
    }

    public function test_index_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('field-layouts'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_index_renders(): void
    {
        FieldLayout::factory()->count(2)->create();

        $this->actingAs($this->admin)->get(route('field-layouts'))->assertOk();
    }

    public function test_create_renders(): void
    {
        $this->actingAs($this->admin)->get(route('field-layouts.create'))->assertOk();
    }

    public function test_edit_renders(): void
    {
        $layout = FieldLayout::factory()->create();

        $this->actingAs($this->admin)->get(route('field-layouts.edit', $layout->id))->assertOk();
    }

    public function test_edit_returns_404_for_missing_layout(): void
    {
        $this->actingAs($this->admin)->get(route('field-layouts.edit', 999999))->assertNotFound();
    }

    public function test_confirm_renders(): void
    {
        $layout = FieldLayout::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('field-layouts.confirm', $layout->id))
            ->assertOk()
            ->assertSee('Delete Field Layout');
    }

    public function test_confirm_missing_layout_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->get(route('field-layouts.confirm', 999999))
            ->assertRedirect(route('field-layouts'))
            ->assertSessionHas('failure');
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_layout_and_redirects_to_edit(): void
    {
        $this->actingAs($this->admin)
            ->post(route('field-layouts.store'), [
                'name' => 'Article Layout',
                'handle' => 'article-layout',
            ])
            ->assertSessionHas('success')
            ->assertRedirectContains('/admin/field-layouts/');

        $this->assertDatabaseHas('field_layouts', ['handle' => 'article-layout']);
    }

    public function test_store_requires_name_and_handle(): void
    {
        $this->actingAs($this->admin)
            ->post(route('field-layouts.store'), [])
            ->assertSessionHasErrors(['name', 'handle']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_layout_and_redirects(): void
    {
        $layout = FieldLayout::factory()->create(['name' => 'Old']);

        $this->actingAs($this->admin)
            ->put(route('field-layouts.update', $layout->id), [
                'name' => 'New Name',
                'handle' => $layout->handle,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_layouts', ['id' => $layout->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_layout(): void
    {
        $this->actingAs($this->admin)
            ->put(route('field-layouts.update', 999999), ['name' => 'Nope', 'handle' => 'nope'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_layout_and_redirects(): void
    {
        $layout = FieldLayout::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.destroy', $layout->id), ['confirm_removal' => 1])
            ->assertRedirect(route('field-layouts'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('field_layouts', ['id' => $layout->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $layout = FieldLayout::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.destroy', $layout->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('field_layouts', ['id' => $layout->id]);
    }

    public function test_destroy_missing_layout_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('field-layouts.destroy', 999999), ['confirm_removal' => 1])
            ->assertRedirect(route('field-layouts'))
            ->assertSessionHas('failure');
    }
}
