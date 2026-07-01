<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;
use AdAstra\Traits\Field\ValidatesAgainstOptions;

class Select extends AbstractField
{
    use ValidatesAgainstOptions;

    protected string $handle = 'select';

    protected string $name = 'Select';

    protected array $rules = [
        'nullable',
        'string',
    ];

    protected array $settings_form = [
        'options' => [
            'type' => 'key_value',
            'label' => 'Options',
            'instructions' => 'Key/label pairs for the dropdown. At least one option is required.',
            'default' => [],
            'rules' => 'required|array|min:1'
        ],
        'placeholder' => [
            'type' => 'text',
            'label' => 'Placeholder',
            'instructions' => 'First empty option label, e.g. "— Choose —".',
            'default' => null,
            'rules' => 'nullable|string|max:255'
        ],
        'default' => [
            'type' => 'text',
            'label' => 'Default Value',
            'instructions' => 'Pre-selected option key.',
            'default' => null,
            'rules' => 'nullable|string|max:255'
        ],
        'strict_options' => [
            'type' => 'toggle',
            'label' => 'Strict Options',
            'instructions' => 'Reject entry saves when the stored value is no longer a valid option.',
            'default' => false,
            'rules' => 'nullable|boolean'
        ],
    ];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function validate(mixed $value): bool|string
    {
        return $this->validateAgainstOptions($value);
    }

    public function render(array $params): string
    {
        $params['options'] = $this->getSetting('options', []);
        $params['placeholder'] = $this->getSetting('placeholder');

        return view('_fields.select', $params)->render();
    }
}
