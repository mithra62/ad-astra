<?php

namespace App\Traits;

use App\Models\UserSchema;

trait UserSchemaRules
{
    /**
     * @return array
     */
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

    /**
     * @return array
     */
    public function schemaFieldAttributes(): array
    {
        $attributes = [];
        $schema = UserSchema::resolved();
        if (!$schema->fieldLayout) {
            return $attributes;
        }

        foreach ($schema->fieldLayout->fields() as $field) {
            $attributes["fields.{$field->slug}"] = $field->name;
        }

        return $attributes;
    }

    protected function schemaFieldMessages(): array
    {
        return [];
    }
}
