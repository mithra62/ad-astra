<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Html extends AbstractField
{
    protected string $handle = 'html';
    protected string $name = 'HTML';

    protected array $rules = [
        'nullable',
    ];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.html', $params)->render();
    }
}
