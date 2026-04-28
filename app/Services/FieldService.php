<?php

namespace App\Services;

use App\Models\Field\Type as FieldType;
use Illuminate\Support\Collection;

class FieldService extends AbstractService
{
    public function getFieldOptions(): array
    {
        $fields = $this->getAllFieldTypes();
        $return = [];
        foreach ($fields as $field) {
            $return[$field->handle()] = $field->name();
        }

        ksort($return);

        return $return;
    }

    /**
     * @return Collection
     */
    public function getAllFieldTypes(): Collection
    {
        $fields = FieldType::all();
        $return = [];
        if ($fields) {
            foreach ($fields as $field) {
                $return[] = $field->instance();
            }
        }

        return collect($return);
    }
}
