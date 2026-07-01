<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Field as FieldModel;
use AdAstra\Models\FieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Concerns\MakesFieldTestFixtures;
use Tests\TestCase;

class FieldSettingsSaveTest extends TestCase
{
    use MakesFieldTestFixtures;
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Select — options round-trip
    // -------------------------------------------------------------------------

    public function test_select_options_are_stored_in_db_after_create(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->selectType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), [
                'group_id' => $group->id,
                'field_type_id' => $type->id,
                'name' => 'Colour',
                'handle' => 'colour',
                'settings' => [
                    'options' => [
                        ['key' => 'red', 'label' => 'Red'],
                        ['key' => 'blue', 'label' => 'Blue'],
                    ],
                ],
            ])
            ->assertRedirect();

        $field = FieldModel::where('handle', 'colour')->first();
        $this->assertNotNull($field);

        $options = $field->settings['options'] ?? [];
        $this->assertCount(2, $options);
        $this->assertSame('red', $options[0]['key']);
        $this->assertSame('blue', $options[1]['key']);
    }

    // -------------------------------------------------------------------------
    // Text — placeholder updated via edit
    // -------------------------------------------------------------------------

    public function test_text_placeholder_is_persisted_after_update(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => ['placeholder' => 'Old placeholder'],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->put(route('fields.update', $field->id), [
                'field_type_id' => $type->id,
                'name' => $field->name,
                'handle' => $field->handle,
                'settings' => ['placeholder' => 'New placeholder'],
            ])
            ->assertRedirect();

        $this->assertSame('New placeholder', $field->fresh()->settings['placeholder']);
    }

    // -------------------------------------------------------------------------
    // Slider — min/max stored
    // -------------------------------------------------------------------------

    public function test_slider_min_and_max_are_stored_in_db(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), [
                'group_id' => $group->id,
                'field_type_id' => $type->id,
                'name' => 'Rating',
                'handle' => 'rating',
                'settings' => ['min' => 1, 'max' => 10, 'step' => 1],
            ])
            ->assertRedirect();

        $field = FieldModel::where('handle', 'rating')->first();
        $this->assertNotNull($field);
        $this->assertSame(1, (int)($field->settings['min'] ?? null));
        $this->assertSame(10, (int)($field->settings['max'] ?? null));
    }

    // -------------------------------------------------------------------------
    // StructuredRows — columns visible on edit page after save
    // -------------------------------------------------------------------------

    public function test_structured_rows_columns_appear_in_edit_page_after_save(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->structuredRowsType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => [
                'columns' => [
                    ['handle' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['handle' => 'qty', 'label' => 'Qty', 'type' => 'number'],
                ],
            ],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->get(route('fields.edit', $field->id))
            ->assertOk()
            ->assertSee('title', false)
            ->assertSee('qty', false);
    }

    // -------------------------------------------------------------------------
    // strict_options — admin warning is flashed when field has existing values
    // -------------------------------------------------------------------------

    public function test_strict_options_warning_is_flashed_when_field_has_existing_values(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->selectType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => ['options' => [['key' => 'red', 'label' => 'Red']]],
        ]);
        $field->groups()->attach($group);

        FieldValue::factory()->create(['field_id' => $field->id, 'value_text' => 'red']);

        $this->actingAs($user)
            ->put(route('fields.update', $field->id), [
                'field_type_id' => $type->id,
                'name' => $field->name,
                'handle' => $field->handle,
                'settings' => [
                    'options' => [['key' => 'red', 'label' => 'Red']],
                    'strict_options' => true,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    // -------------------------------------------------------------------------
    // normaliseKeyValue — label-only rows are silently dropped
    // -------------------------------------------------------------------------

    public function test_label_only_options_are_dropped_during_normalisation(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->selectType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), [
                'group_id' => $group->id,
                'field_type_id' => $type->id,
                'name' => 'Status',
                'handle' => 'status',
                'settings' => [
                    'options' => [
                        ['key' => 'active', 'label' => 'Active'],
                        ['key' => '', 'label' => 'Orphan'],
                    ],
                ],
            ])
            ->assertRedirect();

        $field = FieldModel::where('handle', 'status')->first();
        $options = $field->settings['options'] ?? [];
        $this->assertCount(1, $options);
        $this->assertSame('active', $options[0]['key']);
    }
}
