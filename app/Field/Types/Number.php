<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Number extends AbstractField
{
    protected string $handle = 'number';

    protected string $name = 'Number';

    protected array $rules = [
        'numeric',
    ];

    public function storageColumn(): string
    {
        return $this->getSetting('decimals', 0) > 0
            ? 'value_float'
            : 'value_integer';
    }

    public function render(array $params): string
    {
        return view('_fields.number', $params)->render();
    }
}
