<?php

namespace App\Traits;

trait UserSchemaRules
{


    protected function schemaFieldRules(): array
    {
        $rules = [];
        $schema = \App\Models\UserSchema::instance()->load('fieldLayout.tabs.elements.field.fieldType');

        if (!$schema->fieldLayout) {
            return $rules;
        }

        foreach ($schema->fieldLayout->tabs as $tab) {
            foreach ($tab->elements as $element) {
                $field = $element->field;
                $key = "fields.{$field->slug}";
                $fieldRules = $element->required ? ['required'] : ['nullable'];

                $fieldRules = array_merge($fieldRules, $this->rulesForFieldType(
                    $field->fieldType->object,
                    $field->settings ?? []
                ));

                $rules[$key] = $fieldRules;
            }
        }

        return $rules;
    }

    protected function rulesForFieldType(string $class, array $settings): array
    {
        $map = [
            \App\Field\Types\Text::class => ['string', 'max:' . ($settings['maxLength'] ?? 255)],
            \App\Field\Types\Textarea::class => ['string', 'max:' . ($settings['maxLength'] ?? 65535)],
            \App\Field\Types\Checkbox::class => ['boolean'],
            \App\Field\Types\Date::class => ['date'],
            \App\Field\Types\Number::class => ['numeric'],
            \App\Field\Types\Relationship::class => ['array'],
        ];

        return $map[$class] ?? ['string'];
    }
}
