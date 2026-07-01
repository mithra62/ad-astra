<?php

namespace Tests\Feature\Admin;

use AdAstra\Field\Types\Text;
use AdAstra\Models\Field as FieldModel;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\Role;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldValidationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_store_fails_when_max_length_is_not_an_integer(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['max_length' => 'abc'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.max_length']);
    }

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function textType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'handle' => 'text', 'settings' => []]
        );
    }

    private function makeGroup(): FieldGroup
    {
        return FieldGroup::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Store — settings validation
    // -------------------------------------------------------------------------

    private function basePayload(FieldType $type, FieldGroup $group, array $overrides = []): array
    {
        return array_merge([
            'group_id' => $group->id,
            'field_type_id' => $type->id,
            'name' => 'Test Field',
            'handle' => 'test_field',
        ], $overrides);
    }

    public function test_store_fails_when_max_length_is_below_minimum(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['max_length' => 0],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.max_length']);
    }

    public function test_store_passes_with_valid_settings(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['placeholder' => 'Enter text…', 'max_length' => 200],
            ]))
            ->assertRedirect();
    }

    public function test_store_passes_when_no_settings_submitted(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group))
            ->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // Update — settings validation inherited
    // -------------------------------------------------------------------------

    public function test_update_fails_when_settings_invalid(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => [],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->put(route('fields.update', $field->id), [
                'field_type_id' => $type->id,
                'name' => $field->name,
                'handle' => $field->handle,
                'settings' => ['max_length' => 'not-a-number'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.max_length']);
    }

    public function test_update_passes_with_valid_settings(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => [],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->put(route('fields.update', $field->id), [
                'field_type_id' => $type->id,
                'name' => $field->name,
                'handle' => $field->handle,
                'settings' => ['placeholder' => 'Updated', 'max_length' => 100],
            ])
            ->assertRedirect();
    }
}
