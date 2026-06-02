<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Boolean extends AbstractField
{
    protected string $handle = 'boolean';

    protected string $name = 'Boolean';

    protected array $rules = [
        'required',
        'boolean',
        'in:true,false,1,0'
    ];

    protected array $settings_form = [
        'default' => [
            'type' => 'toggle',
            'label' => 'Checked by default',
            'default' => false,
            'rules' => 'nullable|boolean'
        ],
        'label_on' => [
            'type' => 'text',
            'label' => 'On Label',
            'instructions' => 'Label shown when toggled on.',
            'default' => "on",
            'rules' => 'required|string|max:100'
        ],
        'label_off' => [
            'type' => 'text',
            'label' => 'Off Label',
            'instructions' => 'Label shown when toggled off.',
            'default' => 'Off',
            'rules' => 'required|string|max:100'
        ],
    ];

    public function storageColumn(): string
    {
        return 'value_boolean';
    }

    public function cast(mixed $value): bool
    {
        return (bool)$value;
    }

    public function render(array $params): string
    {
        return view('_fields.boolean', $params)->render();
    }
}
