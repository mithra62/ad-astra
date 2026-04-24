<?php

namespace App\Services;

use App\Models\Field\Type AS FieldType;
use Illuminate\Support\Collection;

class FieldService extends AbstractService
{
    /**
     * @return Collection
     */
    public function getAllFieldTypes(): Collection
    {
        $fields = FieldType::all();
        $return = [];
        if($fields) {
            foreach($fields AS $field) {
                $return[] = $field->instance();
            }
        }

        return collect($return);
    }

    public function getFieldOptions(): array
    {
        $fields = $this->getAllFieldTypes();
        $return = [];
        foreach($fields AS $field) {
            $return[$field->handle()] = $field->name();
        }

        ksort($return);

        return $return;
    }
}
