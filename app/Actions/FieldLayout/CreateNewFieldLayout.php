<?php

namespace App\Actions\FieldLayout;

use App\Actions\AbstractAction;
use App\Models\FieldLayout;

class CreateNewFieldLayout extends AbstractAction
{
    public function create(array $input): FieldLayout
    {
        $layout = FieldLayout::create([
            'name' => $input['name'],
            'handle' => $input['handle'],
        ]);

        $layout->fieldGroups()->sync($input['field_groups'] ?? []);

        return $layout->fresh();
    }
}
