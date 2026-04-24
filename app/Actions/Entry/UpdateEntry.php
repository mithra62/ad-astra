<?php

namespace App\Actions\Entry;

use App\Actions\AbstractAction;
use App\Facades\Content;
use App\Models\Entry;

class UpdateEntry extends AbstractAction
{
    public function update(Entry $entry, array $input): Entry
    {
        return Content::update($entry, $input);
    }
}
