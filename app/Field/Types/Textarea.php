<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Textarea extends AbstractField
{
    protected string $handle = 'textarea';

    protected string $name = 'Textarea';

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.textarea', $params)->render();
    }
}
