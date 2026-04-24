<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class EmailAddress extends AbstractField
{
    protected string $handle = 'email_address';

    protected string $name = 'Email';

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        //$params['field'] = $this;
        return view('_fields.email', $params)->render();
    }
}
