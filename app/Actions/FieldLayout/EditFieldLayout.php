<?php

namespace App\Actions\FieldLayout;

use App\Actions\AbstractAction;
use App\Models\FieldLayout;

class EditFieldLayout extends AbstractAction
{
    public function edit(FieldLayout $layout, array $input): FieldLayout
    {
        $layout->update([
            'name' => $input['name'],
        ]);

        return $layout->fresh();
    }
}
