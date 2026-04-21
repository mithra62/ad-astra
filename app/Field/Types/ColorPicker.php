<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class ColorPicker extends AbstractField
{
    protected string $handle = 'color_picker';

    protected string $name = 'Color Picker';

    public function storageColumn(): string
    {
        return 'value_text';
    }
}
