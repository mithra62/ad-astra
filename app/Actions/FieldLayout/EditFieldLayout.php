<?php

namespace App\Actions\FieldLayout;

use App\Actions\AbstractAction;
use App\Models\FieldLayout;

class EditFieldLayout extends AbstractAction
{
    public function edit(FieldLayout $layout, array $input): FieldLayout
    {
        $layout->update($input);
        $layout->fieldGroups()->sync($input['field_groups'] ?? []);

        return $layout->fresh();
    }
}
