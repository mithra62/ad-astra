<?php

namespace App\Traits;

use App\Models\UserSchema;

trait UserSchemaRules
{
    protected function schemaFieldRules(): array
    {
        $rules = [];
        $schema = UserSchema::resolved();

        if (!$schema->fieldLayout) {
            return $rules;
        }

        foreach ($schema->fieldLayout->tabs as $tab) {
            foreach ($tab->elements as $element) {
                $field = $element->field;
                $key = "fields.{$field->slug}";
                $fieldRules = $element->required ? ['required'] : ['nullable'];

                $rules[$key] = array_merge($fieldRules, $field->fieldType->instance()->getRules());
            }
        }

        return $rules;
    }
}
