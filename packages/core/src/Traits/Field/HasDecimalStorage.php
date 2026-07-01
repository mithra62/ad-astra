<?php

namespace AdAstra\Traits\Field;

trait HasDecimalStorage
{
    public function storageColumn(): string
    {
        return ((int)$this->getSetting('decimals', 0)) > 0
            ? 'value_float'
            : 'value_integer';
    }
}
