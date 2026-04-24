<?php

namespace App\Actions\FieldLayout\Tab;

use App\Actions\AbstractAction;
use App\Models\FieldLayout\Tab;

class DeleteTab extends AbstractAction
{
    public function delete(Tab $tab): bool
    {
        return $tab->delete();
    }
}
