<?php

namespace App\Actions\FieldLayout;

use App\Actions\AbstractAction;
use App\Models\FieldLayout;

class DeleteFieldLayout extends AbstractAction
{
    public function delete(FieldLayout $layout): bool
    {
        return $layout->delete();
    }
}
