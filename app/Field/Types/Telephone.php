<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Telephone extends AbstractField
{
    protected string $handle = 'telephone';

    protected string $name = 'Telephone';

    protected array $rules = [
        'string',
        'telephone',
    ];

    protected array $settings_form = [];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.telephone', $params)->render();
    }
}
