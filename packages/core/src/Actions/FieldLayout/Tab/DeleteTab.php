<?php

namespace AdAstra\Actions\FieldLayout\Tab;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\FieldLayout\Tab;

class DeleteTab extends AbstractAction
{
    public function delete(Tab $tab): bool
    {
        return $tab->delete();
    }
}
