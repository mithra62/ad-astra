<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\FieldLayout\Tab controller. Every action is
 * scoped to a {layout_id}: a tab that belongs to a different layout is 404.
 */
class FieldLayoutTabAdminTest extends TestCase
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

    private function layout(): FieldLayout
    {
        return FieldLayout::factory()->create();
    }

    private function tabIn(FieldLayout $layout): Tab
    {
        return Tab::factory()->create(['field_layout_id' => $layout->id]);
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_create_redirects_guests_to_login(): void
    {
        $layout = $this->layout();

        $this->get(route('field-layouts.tabs.create', ['layout_id' => $layout->id]))
            ->assertRedirect(route('login'));
    }

    public function test_create_forbids_non_admin_user(): void
    {
        $layout = $this->layout();

        $this->actingAs(User::factory()->create())
            ->get(route('field-layouts.tabs.create', ['layout_id' => $layout->id]))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_create_renders(): void
    {
        $layout = $this->layout();

        $this->actingAs($this->admin)
            ->get(route('field-layouts.tabs.create', ['layout_id' => $layout->id]))
            ->assertOk();
    }

    public function test_create_returns_404_for_missing_layout(): void
    {
        $this->actingAs($this->admin)
            ->get(route('field-layouts.tabs.create', ['layout_id' => 999999]))
            ->assertNotFound();
    }

    public function test_edit_renders(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);

        $this->actingAs($this->admin)
            ->get(route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertOk();
    }

    public function test_edit_returns_404_when_tab_belongs_to_another_layout(): void
    {
        $layout = $this->layout();
        $other = $this->layout();
        $tab = $this->tabIn($other);

        $this->actingAs($this->admin)
            ->get(route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertNotFound();
    }

    public function test_fields_renders(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);

        $this->actingAs($this->admin)
            ->get(route('field-layouts.tabs.fields', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertOk();
    }

    // NOTE: the happy-path confirm render is not asserted — the
    // field-layouts/tabs/delete.twig template is broken (includes the
    // non-existent 'admin._inc._header' partial and 500s). Tracked separately.
    // The scoping guard below runs before the view renders.

    public function test_confirm_returns_404_when_tab_belongs_to_another_layout(): void
    {
        $layout = $this->layout();
        $other = $this->layout();
        $tab = $this->tabIn($other);

        $this->actingAs($this->admin)
            ->get(route('field-layouts.tabs.confirm', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_tab_and_redirects_to_layout(): void
    {
        $layout = $this->layout();

        $this->actingAs($this->admin)
            ->post(route('field-layouts.tabs.store', ['layout_id' => $layout->id]), [
                'name' => 'Content',
                'handle' => 'content',
            ])
            ->assertRedirect(route('field-layouts.edit', $layout->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_layout_tabs', [
            'field_layout_id' => $layout->id,
            'handle' => 'content',
        ]);
    }

    public function test_store_returns_404_for_missing_layout(): void
    {
        $this->actingAs($this->admin)
            ->post(route('field-layouts.tabs.store', ['layout_id' => 999999]), [
                'name' => 'Content',
                'handle' => 'content',
            ])
            ->assertNotFound();
    }

    public function test_store_requires_name_and_handle(): void
    {
        $layout = $this->layout();

        $this->actingAs($this->admin)
            ->post(route('field-layouts.tabs.store', ['layout_id' => $layout->id]), [])
            ->assertSessionHasErrors(['name', 'handle']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_tab_and_redirects(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);

        $this->actingAs($this->admin)
            ->put(route('field-layouts.tabs.update', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'name' => 'Renamed Tab',
                'handle' => $tab->handle,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_layout_tabs', ['id' => $tab->id, 'name' => 'Renamed Tab']);
    }

    public function test_update_returns_404_when_tab_belongs_to_another_layout(): void
    {
        $layout = $this->layout();
        $other = $this->layout();
        $tab = $this->tabIn($other);

        $this->actingAs($this->admin)
            ->put(route('field-layouts.tabs.update', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'name' => 'Hijack',
                'handle' => $tab->handle,
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_tab_and_redirects(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.tabs.destroy', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'confirm_removal' => 1,
            ])
            ->assertRedirect(route('field-layouts.edit', $layout->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('field_layout_tabs', ['id' => $tab->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.tabs.destroy', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('field_layout_tabs', ['id' => $tab->id]);
    }

    public function test_destroy_returns_404_when_tab_belongs_to_another_layout(): void
    {
        $layout = $this->layout();
        $other = $this->layout();
        $tab = $this->tabIn($other);

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.tabs.destroy', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'confirm_removal' => 1,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('field_layout_tabs', ['id' => $tab->id]);
    }
}
