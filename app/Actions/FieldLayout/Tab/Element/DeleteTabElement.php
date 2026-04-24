<?php

namespace App\Actions\FieldLayout\Tab\Element;

use App\Actions\AbstractAction;
use App\Models\FieldLayout\TabElement;

class DeleteTabElement extends AbstractAction
{
    public function delete(TabElement $element): bool
    {
        return $element->delete();
    }
}
