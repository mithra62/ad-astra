<?php

namespace AdAstra\Actions\Field;

use AdAstra\Actions\Field\Concerns\FiltersFieldSettings;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Group;
use AdAstra\Models\Field\Type as FieldType;

class CreateNewField
{
    use FiltersFieldSettings;

    public function createByGroup(array $input): Field
    {
        $input = $this->applySettingsFilter($input);
        $group = Group::find($input['group_id']);
        return $group->fields()->create($input);
    }

    private function applySettingsFilter(array $input): array
    {
        $typeId = $input['field_type_id'] ?? null;
        if ($typeId && $type = FieldType::find($typeId)) {
            $input['settings'] = $this->filterSettings($input['settings'] ?? [], $type->instance());
        }
        return $input;
    }

    public function create(array $input): Field
    {
        $input = $this->applySettingsFilter($input);
        return Field::create($input);
    }
}
