<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Date extends AbstractField
{
    public function storageColumn(): string
    {
        return 'value_date';
    }
}
