<?php

namespace Tests\Feature\Admin\Concerns;

use AdAstra\Field\Types\RadioGroup;
use AdAstra\Field\Types\Select;
use AdAstra\Field\Types\Slider;
use AdAstra\Field\Types\StructuredRows;
use AdAstra\Field\Types\Text;
use AdAstra\Field\Types\Users;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\Role;
use AdAstra\Models\User;

trait MakesFieldTestFixtures
{
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

    private function fieldType(string $class, string $handle, string $name): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => $class],
            ['name' => $name, 'handle' => $handle, 'settings' => []]
        );
    }

    private function selectType(): FieldType
    {
        return $this->fieldType(Select::class, 'select', 'Select');
    }

    private function textType(): FieldType
    {
        return $this->fieldType(Text::class, 'text', 'Text');
    }

    private function sliderType(): FieldType
    {
        return $this->fieldType(Slider::class, 'slider', 'Slider');
    }

    private function structuredRowsType(): FieldType
    {
        return $this->fieldType(StructuredRows::class, 'structured_rows', 'Structured Rows');
    }

    private function usersType(): FieldType
    {
        return $this->fieldType(Users::class, 'users', 'Users');
    }

    private function radioGroupType(): FieldType
    {
        return $this->fieldType(RadioGroup::class, 'radio_group', 'Radio Group');
    }

    private function basePayload(FieldType $type, FieldGroup $group, array $overrides = []): array
    {
        return array_merge([
            'group_id' => $group->id,
            'field_type_id' => $type->id,
            'name' => 'Test Field',
            'handle' => 'test_field',
        ], $overrides);
    }
}
