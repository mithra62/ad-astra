<?php

namespace App\Observers;

use App\Models\Entry;
use App\Models\Status;

class StatusObserver
{
    public function updating(Status $status): void
    {
        if ($status->isDirty('is_public')) {
            Entry::where('status_id', $status->id)
                ->update(['status_is_public' => $status->is_public]);
        }
    }
}
