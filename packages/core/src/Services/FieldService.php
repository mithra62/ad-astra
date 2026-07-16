<?php

namespace AdAstra\Services;

use AdAstra\Models\Field\Type as FieldType;
use Illuminate\Support\Collection;

class FieldService extends AbstractService
{
    public function getFieldOptions(): array
    {
        $fields = $this->getAllFieldTypes();
        $return = [];
        foreach ($fields as $field_id => $field) {
            $return[$field_id] = $field->name();
        }

        //sort($return);

        return $return;
    }

    /**
     * @return Collection
     */
    public function getAllFieldTypes(): Collection
    {
        $fields = FieldType::orderBy('name')->get();
        $return = [];
        if ($fields) {
            foreach ($fields as $field) {
                $return[$field->id] = $field->instance();
            }
        }

        return collect($return);
    }

    public function getFieldType(string $handle): ?FieldType
    {
        return FieldType::where('handle', $handle)->first();
    }
}
