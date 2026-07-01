<?php

namespace AdAstra\Http\Requests;

use AdAstra\Models\FieldLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;

class FormRequest extends LaravelFormRequest
{
    public function schemaFieldAttributes(?Model $schema): array
    {
        $layout = $this->layoutFrom($schema);
        $attributes = [];
        if (!$layout) {
            return $attributes;
        }

        foreach ($layout->fields() as $field) {
            $attributes["fields.{$field->handle}"] = $field->name;
        }

        return $attributes;
    }

    protected function schemaFieldRules(?Model $schema): array
    {
        $layout = $this->layoutFrom($schema);
        $rules = [];
        if (!$layout) {
            return $rules;
        }

        foreach ($layout->tabs as $tab) {
            foreach ($tab->elements as $element) {
                $field = $element->field;
                $key = "fields.{$field->handle}";
                $fieldRules = $element->required ? ['required'] : ['nullable'];

                $rules[$key] = array_merge($fieldRules, $field->typeInstance()->getRules());
            }
        }

        return $rules;
    }

    protected function schemaFieldMessages(?Model $schema): array
    {
        return [];
    }

    private function layoutFrom(?Model $schema): ?FieldLayout
    {
        if (!$schema) {
            return null;
        }

        if ($schema instanceof FieldLayout) {
            return $schema;
        }

        return $schema->fieldLayout ?? null;
    }
}
