<?php

namespace AdAstra\Actions\FieldLayout;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\FieldLayout;

class EditFieldLayout extends AbstractAction
{
    public function edit(FieldLayout $layout, array $input): FieldLayout
    {
        $layout->update($input);
        $layout->fieldGroups()->sync($input['field_groups'] ?? []);

        return $layout->fresh();
    }
}
