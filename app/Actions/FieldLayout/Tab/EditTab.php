<?php

namespace App\Actions\FieldLayout\Tab;

use App\Actions\AbstractAction;
use App\Models\FieldLayout\Tab;

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
