<?php

namespace App\Actions\FieldLayout\Tab;

use App\Actions\AbstractAction;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;

class CreateNewTab extends AbstractAction
{
    public function create(FieldLayout $layout, array $input): Tab
    {
        return $layout->tabs()->create([
            'name' => $input['name'],
            'handle' => $input['handle'],
            'sort_order' => $input['sort_order'] ?? 0,
        ]);
    }
}
