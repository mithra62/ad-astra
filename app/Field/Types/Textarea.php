<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Textarea extends AbstractField
{
    protected string $handle = 'textarea';

    protected string $name = 'Textarea';

    protected array $settings_form = [
        'placeholder' => [
            'type' => 'text',
            'label' => 'Placeholder',
            'instructions' => 'Shown inside the textarea when empty.',
            'default' => null,
            'rules' => 'nullable|string|max:255'
        ],
        'max_length' => [
            'type' => 'number',
            'label' => 'Max Length',
            'default' => null,
            'rules' => 'nullable|integer|min:1'
        ],
        'rows' => [
            'type' => 'number',
            'label' => 'Rows',
            'instructions' => 'Visible row count for the textarea.',
            'default' => 4,
            'rules' => 'nullable|integer|min:1|max:50'
        ],
    ];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.textarea', $params)->render();
    }
}
