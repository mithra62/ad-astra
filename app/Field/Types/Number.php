<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Number extends AbstractField
{
    public function storageColumn(): string
    {
        return $this->getSetting('decimals', 0) > 0
            ? 'value_float'
            : 'value_integer';
    }
}
