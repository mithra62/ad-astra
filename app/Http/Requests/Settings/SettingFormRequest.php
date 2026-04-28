<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;

/**
 * Base FormRequest for settings forms.
 *
 * Provides shared helpers for building validation rules and attribute labels
 * from the typed field definitions in config/settings.php.
 *
 * Subclasses supply authorize(), rules(), attributes(), and settingsPayload().
 */
abstract class SettingFormRequest extends FormRequest
{
    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Build a Laravel rules array from a flat list of field definitions.
     *
     * - Boolean fields are excluded (normalised to true/false, not validated).
     * - Fields with no 'rules' key are excluded.
     * - 'nullable' is auto-prepended for any field that does not declare 'required',
     *   so that optional fields accept empty submissions without failing validation.
     *
     * @param  array<int, array<string, mixed>> $fields
     * @return array<string, array<string>>
     */
    protected function settingRulesFromFields(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? 'text') === 'boolean') {
                continue;
            }

            $fieldRules = $field['rules'] ?? [];
            if (empty($fieldRules)) {
                continue;
            }

            if (! in_array('required', $fieldRules, strict: true)) {
                array_unshift($fieldRules, 'nullable');
            }

            $rules[$field['handle']] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Build a custom attribute map so validation messages use the field label
     * ("Timezone") rather than the raw input key ("timezone").
     *
     * @param  array<int, array<string, mixed>> $fields
     * @return array<string, string>
     */
    protected function settingAttributesFromFields(array $fields): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field['handle']] = $field['label'];
        }

        return $attributes;
    }

    /**
     * Extract and normalise field values from the request for the given field
     * definitions. Only handles present in $fields are extracted; boolean fields
     * are normalised from checkbox presence rather than submitted value.
     *
     * @param  array<int, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    protected function normaliseFields(array $fields): array
    {
        $handles = array_column($fields, 'handle');
        $data    = $this->only($handles);

        foreach ($fields as $field) {
            if (($field['type'] ?? 'text') === 'boolean') {
                $data[$field['handle']] = $this->has($field['handle']);
            }
        }

        return $data;
    }

    /**
     * Return the normalised field payload ready for persistence.
     *
     * Subclasses must implement this so the controller can call a single
     * method to obtain the fully prepared data array.
     *
     * @return array<string, mixed>
     */
    abstract public function settingsPayload(): array;
}
