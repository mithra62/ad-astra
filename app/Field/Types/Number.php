<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Traits\Field\HasDecimalStorage;

class Number extends AbstractField
{
    use HasDecimalStorage;

    protected string $handle = 'number';

    protected string $name = 'Number';

    protected array $rules = [
        'numeric',
    ];

    protected array $settings_form = [
        'min' => [
            'type' => 'number',
            'label' => 'Minimum',
            'default' => null,
            'rules' => 'nullable|numeric'
        ],
        'max' => [
            'type' => 'number',
            'label' => 'Maximum',
            'default' => null,
            'rules' => 'nullable|numeric'
        ],
        'step' => [
            'type' => 'number',
            'label' => 'Step',
            'default' => null,
            'rules' => 'nullable|numeric|min:0'
        ],
        'decimals' => [
            'type' => 'number',
            'label' => 'Decimal Places',
            'instructions' => 'Set to 0 for integers, >0 for floats.',
            'default' => 0,
            'rules' => 'nullable|integer|min:0|max:10'
        ],
        'default' => [
            'type' => 'number',
            'label' => 'Default Value',
            'default' => null,
            'rules' => 'nullable|numeric'
        ],
    ];

    public function render(array $params): string
    {
        return view('_fields.number', $params)->render();
    }
}
