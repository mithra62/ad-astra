<?php

namespace AdAstra\Actions\FieldLayout\Tab;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\FieldLayout\Tab;

class EditTab extends AbstractAction
{
    public function edit(Tab $tab, array $input): Tab
    {
        $tab->update([
            'name' => $input['name'],
            'sort_order' => $input['sort_order'] ?? 0,
        ]);

        return $tab->fresh();
    }
}
