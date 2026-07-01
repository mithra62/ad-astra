<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Field;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\FieldLayout\TabElement controller. Actions are
 * scoped through the layout -> tab -> element chain; a mismatch at any level is
 * a 404 (an element in a different tab, or a tab in a different layout).
 */
class FieldLayoutTabElementAdminTest extends TestCase
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

    private function elementIn(Tab $tab): TabElement
    {
        return TabElement::factory()->create([
            'field_layout_tab_id' => $tab->id,
            'field_id' => Field::factory()->create()->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_store_redirects_guests_to_login(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);

        $this->post(route('field-layouts.tabs.elements.store', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [])
            ->assertRedirect(route('login'));
    }

    public function test_confirm_forbids_non_admin_user(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $element = $this->elementIn($tab);

        $this->actingAs(User::factory()->create())
            ->get(route('field-layouts.tabs.elements.confirm', [
                'layout_id' => $layout->id, 'tab_id' => $tab->id, 'element_id' => $element->id,
            ]))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_adds_element_to_tab_and_redirects(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $field = Field::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('field-layouts.tabs.elements.store', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'field_id' => $field->id,
            ])
            ->assertRedirect(route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_layout_tab_elements', [
            'field_layout_tab_id' => $tab->id,
            'field_id' => $field->id,
        ]);
    }

    public function test_store_requires_field_id(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);

        $this->actingAs($this->admin)
            ->post(route('field-layouts.tabs.elements.store', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [])
            ->assertSessionHasErrors('field_id');
    }

    public function test_store_returns_404_when_tab_belongs_to_another_layout(): void
    {
        $layout = $this->layout();
        $other = $this->layout();
        $tab = $this->tabIn($other);
        $field = Field::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('field-layouts.tabs.elements.store', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'field_id' => $field->id,
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // confirm (render)
    // -------------------------------------------------------------------------

    // NOTE: the happy-path confirm render is not asserted — the
    // field-layouts/tabs/elements/delete.twig template is broken (includes the
    // non-existent 'admin._inc._header' partial and 500s). Tracked separately.
    // The scoping guard below runs before the view renders.

    public function test_confirm_returns_404_when_element_belongs_to_another_tab(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $otherTab = $this->tabIn($layout);
        $element = $this->elementIn($otherTab);

        $this->actingAs($this->admin)
            ->get(route('field-layouts.tabs.elements.confirm', [
                'layout_id' => $layout->id, 'tab_id' => $tab->id, 'element_id' => $element->id,
            ]))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_element_and_redirects(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $element = $this->elementIn($tab);

        $this->actingAs($this->admin)
            ->put(route('field-layouts.tabs.elements.update', [
                'layout_id' => $layout->id, 'tab_id' => $tab->id, 'element_id' => $element->id,
            ]), ['label' => 'Custom Label', 'required' => 1])
            ->assertRedirect(route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_layout_tab_elements', [
            'id' => $element->id,
            'label' => 'Custom Label',
        ]);
    }

    public function test_update_returns_404_when_element_belongs_to_another_tab(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $otherTab = $this->tabIn($layout);
        $element = $this->elementIn($otherTab);

        $this->actingAs($this->admin)
            ->put(route('field-layouts.tabs.elements.update', [
                'layout_id' => $layout->id, 'tab_id' => $tab->id, 'element_id' => $element->id,
            ]), ['label' => 'Nope'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_removes_element_and_redirects(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $element = $this->elementIn($tab);

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.tabs.elements.destroy', [
                'layout_id' => $layout->id, 'tab_id' => $tab->id, 'element_id' => $element->id,
            ]), ['confirm_removal' => 1])
            ->assertRedirect(route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('field_layout_tab_elements', ['id' => $element->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $element = $this->elementIn($tab);

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.tabs.elements.destroy', [
                'layout_id' => $layout->id, 'tab_id' => $tab->id, 'element_id' => $element->id,
            ]), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('field_layout_tab_elements', ['id' => $element->id]);
    }

    // -------------------------------------------------------------------------
    // bulkUpdate
    // -------------------------------------------------------------------------

    public function test_bulk_update_reorders_elements_and_redirects(): void
    {
        $layout = $this->layout();
        $tab = $this->tabIn($layout);
        $element = $this->elementIn($tab);

        $this->actingAs($this->admin)
            ->put(route('field-layouts.tabs.elements.bulk-update', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'elements' => [
                    ['element_id' => $element->id, 'sort_order' => 5, 'label' => 'Reordered'],
                ],
            ])
            ->assertRedirect(route('field-layouts.tabs.fields', ['layout_id' => $layout->id, 'tab_id' => $tab->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_layout_tab_elements', ['id' => $element->id, 'sort_order' => 5]);
    }

    public function test_bulk_update_returns_404_when_tab_belongs_to_another_layout(): void
    {
        $layout = $this->layout();
        $other = $this->layout();
        $tab = $this->tabIn($other);

        $this->actingAs($this->admin)
            ->put(route('field-layouts.tabs.elements.bulk-update', ['layout_id' => $layout->id, 'tab_id' => $tab->id]), [
                'elements' => [],
            ])
            ->assertNotFound();
    }
}
