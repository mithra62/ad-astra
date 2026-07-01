<?php

namespace AdAstra\Actions\FieldLayout\Tab;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;

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
