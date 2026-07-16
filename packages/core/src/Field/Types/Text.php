<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;

class Text extends AbstractField
{
    /**
     * @var string
     */
    protected string $handle = 'text';

    protected string $name = 'Text';

    protected array $rules = [
        'string',
    ];

    protected array $settings_form = [
        'placeholder' => [
            'type' => 'text',
            'label' => 'Placeholder',
            'instructions' => 'Shown inside the input when empty.',
            'default' => null,
            'required' => false,
            'rules' => 'nullable|string|max:255',
        ],
        'max_length' => [
            'type' => 'number',
            'label' => 'Max Length',
            'default' => null,
            'rules' => 'nullable|integer|min:1',
        ],
        'min_length' => [
            'type' => 'number',
            'label' => 'Min Length',
            'default' => null,
            'rules' => 'nullable|integer|min:0',
        ],
    ];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.text', $params)->render();
    }
}
