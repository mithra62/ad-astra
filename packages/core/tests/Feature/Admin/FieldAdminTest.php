<?php

namespace Tests\Feature\Admin;

use AdAstra\Field\Types\Text;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Field controller — the guard/redirect branches
 * (index 404, missing-record 404s, destroy, typeSettings) that the field
 * create/edit render tests do not exercise. The settings-heavy store/update
 * happy paths are covered by the existing FieldSettings* suites.
 */
class FieldAdminTest extends TestCase
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

    private function textType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'settings' => []]
        );
    }

    private function fieldInGroup(): Field
    {
        $group = FieldGroup::factory()->create();
        $field = Field::factory()->create(['field_type_id' => $this->textType()->id]);
        $field->groups()->attach($group->id);

        return $field;
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_create_redirects_guests_to_login(): void
    {
        $group = FieldGroup::factory()->create();

        $this->get(route('fields.create', ['group_id' => $group->id]))
            ->assertRedirect(route('login'));
    }

    public function test_create_forbids_non_admin_user(): void
    {
        $group = FieldGroup::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('fields.create', ['group_id' => $group->id]))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // index (always 404 — not a real page)
    // -------------------------------------------------------------------------

    public function test_index_returns_404(): void
    {
        $this->actingAs($this->admin)->get(route('fields.index'))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // create / show / edit / confirm renders + 404s
    // -------------------------------------------------------------------------

    public function test_create_renders_for_valid_group(): void
    {
        $this->textType();
        $group = FieldGroup::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('fields.create', ['group_id' => $group->id]))
            ->assertOk();
    }

    public function test_create_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)
            ->get(route('fields.create', ['group_id' => 999999]))
            ->assertNotFound();
    }

    public function test_show_renders(): void
    {
        $field = $this->fieldInGroup();

        $this->actingAs($this->admin)->get(route('fields.show', $field->id))->assertOk();
    }

    public function test_show_returns_404_for_missing_field(): void
    {
        $this->actingAs($this->admin)->get(route('fields.show', 999999))->assertNotFound();
    }

    public function test_edit_renders(): void
    {
        $field = $this->fieldInGroup();

        $this->actingAs($this->admin)->get(route('fields.edit', $field->id))->assertOk();
    }

    public function test_edit_returns_404_for_missing_field(): void
    {
        $this->actingAs($this->admin)->get(route('fields.edit', 999999))->assertNotFound();
    }

    public function test_confirm_renders(): void
    {
        $field = $this->fieldInGroup();

        $this->actingAs($this->admin)->get(route('fields.confirm', $field->id))->assertOk();
    }

    public function test_confirm_returns_404_for_missing_field(): void
    {
        $this->actingAs($this->admin)->get(route('fields.confirm', 999999))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_field_and_redirects_to_group(): void
    {
        $field = $this->fieldInGroup();

        $this->actingAs($this->admin)
            ->delete(route('fields.destroy', $field->id), ['confirm_removal' => 1])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('fields', ['id' => $field->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $field = $this->fieldInGroup();

        $this->actingAs($this->admin)
            ->delete(route('fields.destroy', $field->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('fields', ['id' => $field->id]);
    }

    // -------------------------------------------------------------------------
    // typeSettings (AJAX settings panel)
    // -------------------------------------------------------------------------

    public function test_type_settings_renders_panel_for_valid_type(): void
    {
        $type = $this->textType();

        $this->actingAs($this->admin)
            ->get(route('fields.type_settings', ['type_id' => $type->id]))
            ->assertOk();
    }

    public function test_type_settings_requires_type_id(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('fields.type_settings'))
            ->assertStatus(422);
    }

    public function test_type_settings_returns_404_for_missing_type(): void
    {
        $this->actingAs($this->admin)
            ->get(route('fields.type_settings', ['type_id' => 999999]))
            ->assertNotFound();
    }
}
