<?php

namespace AdAstra\Actions\Media\Library;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Media\Library;

class CreateNewMediaLibrary extends AbstractAction
{
    public function create(array $input): Library
    {
        $library = Library::create($input);
        if (!empty($input['category_groups'])) {
            $library->categoryGroups()->sync($input['category_groups']);
        }

        return $library;
    }
}
