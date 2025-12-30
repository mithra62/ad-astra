<?php

namespace App\Actions\Media\Library;

use App\Models\Media\Library;
use App\Actions\AbstractAction;

class CreateNewMediaLibrary extends AbstractAction
{
    public function create(array $input): Library
    {
        $cat_group = Library::create($input);

        return $cat_group;
    }
}
