<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Text extends AbstractField
{
    /**
     * @var string
     */
    protected string $handle = 'text';

    protected string $name = 'Text';

    protected array $rules = [
        'min:255',
        'string',
    ];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.text', $params)->render();
    }
}
