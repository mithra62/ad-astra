<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Text extends AbstractField
{
    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        //$params['field'] = $this;
        return view('_fields.text', $params)->render();
    }
}
