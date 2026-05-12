<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Date extends AbstractField
{
    protected string $handle = 'date';

    protected string $name = 'Date';

    protected array $rules = [
        'date',
    ];

    public function storageColumn(): string
    {
        return 'value_date';
    }

    public function render(array $params): string
    {
        return view('_fields.date', $params)->render();
    }
}
