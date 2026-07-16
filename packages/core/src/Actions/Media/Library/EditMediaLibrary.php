<?php

namespace AdAstra\Actions\Media\Library;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Media\Library;

class EditMediaLibrary extends AbstractAction
{
    public function edit(Library $library, array $input): bool
    {
        $library->categoryGroups()->sync($input['category_groups'] ?? []);

        return $library->update($input);
    }
}
