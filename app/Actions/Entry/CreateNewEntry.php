<?php

namespace App\Actions\Entry;

use App\Actions\AbstractAction;
use App\Facades\Content;
use App\Models\Entry;

class CreateNewEntry extends AbstractAction
{
    public function create(array $input): Entry
    {
        $typeHandle = $input['type_handle'];

        return Content::create($typeHandle, $input);
    }
}
