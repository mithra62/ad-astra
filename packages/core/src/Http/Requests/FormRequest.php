<?php

namespace AdAstra\Http\Requests;

use AdAstra\Models\FieldLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;

class FormRequest extends LaravelFormRequest
{
    /**
     * Read a numeric route parameter as an int.
     *
     * Route parameters come back as object|string|null. Callers here feed them
     * straight into findOrFail(), so a missing or non-numeric value returns 0
     * and produces the same ModelNotFoundException (404) it always has.
     */
    protected function routeId(string $key): int
    {
        $value = $this->route()?->parameter($key);

        return is_numeric($value) ? (int) $value : 0;
    }

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
}
