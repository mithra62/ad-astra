<?php

namespace AdAstra\Actions\FieldLayout\Tab\Element;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\FieldLayout\TabElement;

class DeleteTabElement extends AbstractAction
{
    public function delete(TabElement $element): bool
    {
        return $element->delete();
    }
}
