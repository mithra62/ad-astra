<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Models\Media\Library;

class CreateNewMediaLibrary extends AbstractAction
{
    public function create(array $input): Library
    {
        $library = Library::create($input);
        if (!empty($input['category_groups'])) {
            foreach ($input['category_groups'] as $group) {
                $library->category_groups()->attach($group);
            }
        }

        return $library;
    }
}
