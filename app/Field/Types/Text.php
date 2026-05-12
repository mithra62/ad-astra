<?php

namespace App\Field\Types;

use App\Field\AbstractField;

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
            'required' => false,
            'rules' => 'string',
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
