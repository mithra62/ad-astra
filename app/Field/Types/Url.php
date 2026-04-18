<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Url extends AbstractField
{
    public function storageColumn(): string
    {
        return 'value_text';
    }
}
