<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;

class Url extends AbstractField
{
    protected string $handle = 'url';

    protected string $name = 'URL';

    protected array $rules = [
        'string',
        'url',
    ];

    protected array $settings_form = [];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.url', $params)->render();
    }
}
