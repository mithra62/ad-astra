<?php

namespace App\Actions\Field;

use App\Actions\Field\Concerns\FiltersFieldSettings;
use App\Models\Field;
use App\Models\Field\Group;
use App\Models\Field\Type as FieldType;

class CreateNewField
{
    use FiltersFieldSettings;

    public function createByGroup(array $input): Field
    {
        $input = $this->applySettingsFilter($input);
        $group = Group::find($input['group_id']);
        return $group->fields()->create($input);
    }

    public function create(array $input): Field
    {
        $input = $this->applySettingsFilter($input);
        return Field::create($input);
    }

    private function applySettingsFilter(array $input): array
    {
        $typeId = $input['field_type_id'] ?? null;
        if ($typeId && $type = FieldType::find($typeId)) {
            $input['settings'] = $this->filterSettings($input['settings'] ?? [], $type->instance());
        }
        return $input;
    }
}
