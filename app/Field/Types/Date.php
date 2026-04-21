<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Date extends AbstractField
{
    protected string $handle = 'date';

    protected string $name = 'Date';

    public function storageColumn(): string
    {
        return 'value_date';
    }

    public function render(array $params): string
    {
        //$params['field'] = $this;
        return view('_fields.date', $params)->render();
    }
}
