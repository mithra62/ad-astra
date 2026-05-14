<?php

namespace Tests\Feature\Admin;

use App\Models\Field as FieldModel;
use App\Models\FieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Concerns\MakesFieldTestFixtures;
use Tests\TestCase;

class FieldSettingsValidationTest extends TestCase
{
    use MakesFieldTestFixtures;
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Select — options required
    // -------------------------------------------------------------------------

    public function test_select_fails_when_options_are_empty(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->selectType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['options' => []],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.options']);
    }

    public function test_select_fails_when_options_are_absent(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->selectType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => [],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.options']);
    }

    // -------------------------------------------------------------------------
    // Slider — min/max required
    // -------------------------------------------------------------------------

    public function test_slider_fails_when_min_is_missing(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['max' => 100],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.min']);
    }

    public function test_slider_fails_when_max_is_missing(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['min' => 0],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.max']);
    }

    public function test_slider_passes_with_valid_min_and_max(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['min' => 0, 'max' => 100],
            ]))
            ->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // Users — limit must be >= 0
    // -------------------------------------------------------------------------

    public function test_users_fails_when_limit_is_negative(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->usersType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['limit' => -1],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.limit']);
    }

    public function test_users_passes_when_limit_is_zero(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->usersType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['limit' => 0],
            ]))
            ->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // Type-change guard — blocked when field has existing values
    // -------------------------------------------------------------------------

    public function test_type_change_is_blocked_when_field_has_existing_values(): void
    {
        $user = $this->makeSuperAdmin();
        $textType = $this->textType();
        $sliderType = $this->sliderType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create(['field_type_id' => $textType->id]);
        $field->groups()->attach($group);

        FieldValue::factory()->create(['field_id' => $field->id, 'value_text' => 'hello']);

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);

        $this->actingAs($user)
            ->put(route('fields.update', $field->id), [
                'field_type_id' => $sliderType->id,
                'name' => $field->name,
                'handle' => $field->handle,
                'settings' => ['min' => 0, 'max' => 100],
            ]);
    }
}
