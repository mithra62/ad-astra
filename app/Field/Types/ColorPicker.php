<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class ColorPicker extends AbstractField
{
    public function storageColumn(): string
    {
        return 'value_text';
    }
}
