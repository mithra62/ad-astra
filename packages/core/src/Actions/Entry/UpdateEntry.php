<?php

namespace AdAstra\Actions\Entry;

use AdAstra\Actions\AbstractAction;
use AdAstra\Facades\Content;
use AdAstra\Models\Entry;

class UpdateEntry extends AbstractAction
{
    public function update(Entry $entry, array $input): Entry
    {
        return Content::update($entry, $input);
    }
}
