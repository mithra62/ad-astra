<?php

namespace App\Actions\Media\Library;

use App\Models\Media\Library;
use App\Actions\AbstractAction;

class CreateNewMediaLibrary extends AbstractAction
{
    public function create(array $input): Library
    {
        $library = Library::create($input);
        if(!empty($input['category_groups'])) {
            foreach($input['category_groups'] AS $group) {
                $library->category_groups()->attach($group);
            }
        }

        return $library;
    }
}
