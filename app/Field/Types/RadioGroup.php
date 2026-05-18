<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Traits\Field\ValidatesAgainstOptions;

class RadioGroup extends AbstractField
{
    use ValidatesAgainstOptions;

    protected string $handle = 'radio_group';

    protected string $name = 'Radio Group';

    protected array $rules = [
        'nullable',
        'string',
    ];

    protected array $settings_form = [
        'options' => [
            'type' => 'key_value',
            'label' => 'Options',
            'instructions' => 'Key/label pairs for the radio buttons. At least one option is required.',
            'default' => [],
            'rules' => 'required|array|min:1'
        ],
        'default' => [
            'type' => 'text',
            'label' => 'Default Value',
            'instructions' => 'Pre-selected option key.',
            'default' => null,
            'rules' => 'nullable|string|max:255'
        ],
        'layout' => [
            'type' => 'select',
            'label' => 'Layout',
            'options' => [
                [
                    'value' => 'stacked',
                    'label' => 'Stacked'
                ],
                [
                    'value' => 'inline',
                    'label' => 'Inline'
                ]
            ],
            'default' => 'stacked',
            'rules' => 'nullable|string|in:stacked,inline'
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
        $params['layout'] = $this->getSetting('layout', 'stacked');

        return view('_fields.radio_group', $params)->render();
    }
}
