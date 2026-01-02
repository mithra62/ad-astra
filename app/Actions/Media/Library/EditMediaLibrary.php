<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Models\Media\Library;

class EditMediaLibrary extends AbstractAction
{
    public function edit(Library $library, array $input): bool
    {
        $library->category_groups()->detach();
        if (!empty($input['category_groups'])) {
            foreach ($input['category_groups'] as $cat_group) {
                $library->category_groups()->attach($cat_group);
            }
        }

        return $library->update($input);
    }
}
