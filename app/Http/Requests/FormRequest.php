<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;

class FormRequest extends LaravelFormRequest
{
    public function schemaFieldAttributes(?Model $schema): array
    {
        $attributes = [];
        if (!$schema || !$schema->fieldLayout) {
            return $attributes;
        }

        foreach ($schema->fieldLayout->fields() as $field) {
            $attributes["fields.{$field->handle}"] = $field->name;
        }

        return $attributes;
    }

    protected function schemaFieldRules(?Model $schema): array
    {
        $rules = [];
        if (!$schema || !$schema->fieldLayout) {
            return $rules;
        }

        foreach ($schema->fieldLayout->tabs as $tab) {
            foreach ($tab->elements as $element) {
                $field = $element->field;
                $key = "fields.{$field->handle}";
                $fieldRules = $element->required ? ['required'] : ['nullable'];

                $rules[$key] = array_merge($fieldRules, $field->fieldType->instance()->getRules());
            }
        }

        return $rules;
    }

    protected function schemaFieldMessages(?Model $schema): array
    {
        return [];
    }
}
