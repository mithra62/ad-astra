<?php

namespace AdAstra\Actions\Entry;

use AdAstra\Actions\AbstractAction;
use AdAstra\Facades\Entries;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryMetric;
use Carbon\Carbon;

class RecordEntryMetric extends AbstractAction
{
    public function record(Entry $entry, string $metric, int $value = 1, ?Carbon $date = null): EntryMetric
    {
        return Entries::recordMetric($entry, $metric, $value, $date);
    }
}
