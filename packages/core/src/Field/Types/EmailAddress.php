<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;

class EmailAddress extends AbstractField
{
    protected string $handle = 'email_address';

    protected string $name = 'Email';

    protected array $settings_form = [];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.email', $params)->render();
    }
}
