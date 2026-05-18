<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Models\Media\Library;

class EditMediaLibrary extends AbstractAction
{
    public function edit(Library $library, array $input): bool
    {
        $library->categoryGroups()->sync($input['category_groups'] ?? []);
        $library->fieldGroups()->sync($input['field_groups'] ?? []);

        return $library->update($input);
    }
}
