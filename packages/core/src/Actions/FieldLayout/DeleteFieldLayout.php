<?php

namespace AdAstra\Actions\FieldLayout;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\FieldLayout;

class DeleteFieldLayout extends AbstractAction
{
    public function delete(FieldLayout $layout): bool
    {
        return $layout->delete();
    }
}
