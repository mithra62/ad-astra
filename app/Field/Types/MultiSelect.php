<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Traits\Field\ValidatesAgainstOptions;

class MultiSelect extends AbstractField
{
    use ValidatesAgainstOptions;

    protected string $handle = 'multi_select';

    protected string $name = 'Multi Select';

    protected array $rules = [
        'nullable',
        'array',
    ];

    protected array $settings_form = [
        'options'        => ['type' => 'key_value', 'label' => 'Options', 'instructions' => 'Key/label pairs for selection. At least one option is required.', 'default' => [], 'rules' => 'required|array|min:1'],
        'min'            => ['type' => 'number', 'label' => 'Minimum Selections', 'default' => null, 'rules' => 'nullable|integer|min:0'],
        'max'            => ['type' => 'number', 'label' => 'Maximum Selections', 'default' => null, 'rules' => 'nullable|integer|min:1'],
        'display'        => ['type' => 'select', 'label' => 'Display As', 'options' => [['value' => 'checkboxes', 'label' => 'Checkboxes'], ['value' => 'multiselect', 'label' => 'Multi-select list']], 'default' => 'checkboxes', 'rules' => 'nullable|string|in:checkboxes,multiselect'],
        'strict_options' => ['type' => 'toggle', 'label' => 'Strict Options', 'instructions' => 'Reject entry saves when any stored value is no longer a valid option.', 'default' => false, 'rules' => 'nullable|boolean'],
    ];

    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function cast(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_map('strval', $decoded) : [];
        }

        if (is_array($value)) {
            return array_map('strval', $value);
        }

        return [];
    }

    public function validate(mixed $value): bool|string
    {
        if ($value === null || $value === []) {
            return true;
        }

        $ids = $this->cast($value);
        $min = $this->getSetting('min');
        $max = $this->getSetting('max');

        if ($min !== null && count($ids) < (int) $min) {
            return "At least {$min} option(s) must be selected.";
        }

        if ($max !== null && count($ids) > (int) $max) {
            return "No more than {$max} option(s) may be selected.";
        }

        return $this->validateAgainstOptions($ids);
    }

    public function render(array $params): string
    {
        $params['options'] = $this->getSetting('options', []);
        $params['display'] = $this->getSetting('display', 'checkboxes');

        return view('_fields.multi_select', $params)->render();
    }
}
