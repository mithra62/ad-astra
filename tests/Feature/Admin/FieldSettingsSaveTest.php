<?php

namespace Tests\Feature\Admin;

use App\Field\Types\Select;
use App\Field\Types\Slider;
use App\Field\Types\StructuredRows;
use App\Field\Types\Text;
use App\Models\Field as FieldModel;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function makeGroup(): FieldGroup
    {
        return FieldGroup::factory()->create();
    }

    private function selectType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Select::class],
            ['name' => 'Select', 'handle' => 'select', 'settings' => []]
        );
    }

    private function textType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'handle' => 'text', 'settings' => []]
        );
    }

    private function sliderType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Slider::class],
            ['name' => 'Slider', 'handle' => 'slider', 'settings' => []]
        );
    }

    private function structuredRowsType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => StructuredRows::class],
            ['name' => 'Structured Rows', 'handle' => 'structured_rows', 'settings' => []]
        );
    }

    // -------------------------------------------------------------------------
    // Select — options round-trip
    // -------------------------------------------------------------------------

    public function test_select_options_are_stored_in_db_after_create(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->selectType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), [
                'group_id'      => $group->id,
                'field_type_id' => $type->id,
                'name'          => 'Colour',
                'handle'        => 'colour',
                'settings'      => [
                    'options' => [
                        ['key' => 'red',  'label' => 'Red'],
                        ['key' => 'blue', 'label' => 'Blue'],
                    ],
                ],
            ])
            ->assertRedirect();

        $field = FieldModel::where('handle', 'colour')->first();
        $this->assertNotNull($field);

        $options = $field->settings['options'] ?? [];
        $this->assertCount(2, $options);
        $this->assertSame('red',  $options[0]['key']);
        $this->assertSame('blue', $options[1]['key']);
    }

    // -------------------------------------------------------------------------
    // Text — placeholder updated via edit
    // -------------------------------------------------------------------------

    public function test_text_placeholder_is_persisted_after_update(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->textType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings'      => ['placeholder' => 'Old placeholder'],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->put(route('fields.update', $field->id), [
                'field_type_id' => $type->id,
                'name'          => $field->name,
                'handle'        => $field->handle,
                'settings'      => ['placeholder' => 'New placeholder'],
            ])
            ->assertRedirect();

        $this->assertSame('New placeholder', $field->fresh()->settings['placeholder']);
    }

    // -------------------------------------------------------------------------
    // Slider — min/max stored
    // -------------------------------------------------------------------------

    public function test_slider_min_and_max_are_stored_in_db(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), [
                'group_id'      => $group->id,
                'field_type_id' => $type->id,
                'name'          => 'Rating',
                'handle'        => 'rating',
                'settings'      => ['min' => 1, 'max' => 10, 'step' => 1],
            ])
            ->assertRedirect();

        $field = FieldModel::where('handle', 'rating')->first();
        $this->assertNotNull($field);
        $this->assertSame(1,  (int) ($field->settings['min'] ?? null));
        $this->assertSame(10, (int) ($field->settings['max'] ?? null));
    }

    // -------------------------------------------------------------------------
    // StructuredRows — columns visible on edit page after save
    // -------------------------------------------------------------------------

    public function test_structured_rows_columns_appear_in_edit_page_after_save(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->structuredRowsType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings'      => [
                'columns' => [
                    ['handle' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['handle' => 'qty',   'label' => 'Qty',   'type' => 'number'],
                ],
            ],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->get(route('fields.edit', $field->id))
            ->assertOk()
            ->assertSee('title', false)
            ->assertSee('qty',   false);
    }
}
