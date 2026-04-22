<?php

namespace App\Actions\FieldLayout;

use App\Actions\AbstractAction;
use App\Models\FieldLayout;

class CreateNewFieldLayout extends AbstractAction
{
    public function create(array $input): FieldLayout
    {
        return FieldLayout::create([
            'name' => $input['name'],
        ]);
    }
}
