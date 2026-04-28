<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Boolean extends AbstractField
{
    protected string $handle = 'boolean';

    protected string $name = 'Boolean';

    protected array $rules = [
        'boolean',
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
