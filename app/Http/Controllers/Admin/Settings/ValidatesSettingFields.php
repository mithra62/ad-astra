<?php

namespace App\Http\Controllers\Admin\Settings;

use Illuminate\Http\Request;

/**
 * Shared validation helpers for settings form controllers.
 *
 * Both the system Domain controller and the UserSettings controller validate
 * submitted field values against the rules declared in config/settings.php
 * before persisting anything.
 *
 * Rule resolution:
 *   - Fields with no 'rules' key are not validated (saved as-is).
 *   - 'nullable' is automatically prepended for any field whose rules do not
 *     include 'required', so that optional fields accept empty submissions.
 *   - Boolean fields skip validation entirely — they are normalised to
 *     true/false before saving regardless of what was submitted.
 */
trait ValidatesSettingFields
{
    /**
     * Run Laravel validation against a flat list of field definitions.
     *
     * @param  array<int, array<string, mixed>> $fields   field definition arrays
     * @param  \Illuminate\Http\Request         $request
     */
    protected function validateSettingFields(array $fields, Request $request): void
    {
        $rules      = $this->settingValidationRules($fields);
        $attributes = $this->settingValidationAttributes($fields);

        if (empty($rules)) {
            return;
        }

        $request->validate($rules, [], $attributes);
    }

    /**
     * Build a Laravel validation rules array keyed by the field handle.
     *
     * - Boolean fields are excluded (they are normalised, not validated).
     * - Fields with no 'rules' key are excluded.
     * - 'nullable' is prepended unless the field already declares 'required'.
     *
     * @param  array<int, array<string, mixed>> $fields
     * @return array<string, array<string>>
     */
    protected function settingValidationRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            // Booleans are normalised (0/1) before save — nothing to validate.
            if (($field['type'] ?? 'text') === 'boolean') {
                continue;
            }

            $fieldRules = $field['rules'] ?? [];
            if (empty($fieldRules)) {
                continue;
            }

            // Auto-prepend nullable for optional fields so empty submissions pass.
            if (! in_array('required', $fieldRules, strict: true)) {
                array_unshift($fieldRules, 'nullable');
            }

            $rules[$field['handle']] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Build a custom attribute name map so validation errors use the field
     * label ("Timezone") rather than the input key ("timezone").
     *
     * @param  array<int, array<string, mixed>> $fields
     * @return array<string, string>
     */
    protected function settingValidationAttributes(array $fields): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field['handle']] = $field['label'];
        }

        return $attributes;
    }
}
