<?php

namespace App\Actions\Entry;

use App\Actions\AbstractAction;
use App\Facades\Entries;
use App\Models\Entry;
use App\Models\EntryMetric;
use Carbon\Carbon;

class RecordEntryMetric extends AbstractAction
{
    public function record(Entry $entry, string $metric, int $value = 1, ?Carbon $date = null): EntryMetric
    {
        return Entries::recordMetric($entry, $metric, $value, $date);
    }
}
