<?php

namespace AdAstra\Observers;

use AdAstra\Models\Status;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class StatusObserver
{
    /**
     * On Status update, propagate is_public / handle changes to every model
     * that uses the HasStatus trait. Both columns are denormalized onto
     * consumers for index-backed filtering; this keeps them in sync with the
     * canonical Status row.
     *
     * Bulk updates run inside a transaction. Consumers using SoftDeletes are
     * queried via withTrashed() so soft-deleted rows stay consistent and
     * won't drift if restored later.
     */
    public function updating(Status $status): void
    {
        $changes = [];

        if ($status->isDirty('is_public')) {
            $changes['status_is_public'] = $status->is_public;
        }

        if ($status->isDirty('handle')) {
            $changes['status_handle'] = $status->handle;
        }

        if (empty($changes)) {
            return;
        }

        DB::transaction(function () use ($status, $changes): void {
            foreach (StatusSyncRegistry::consumers() as $consumer) {
                $query = in_array(SoftDeletes::class, class_uses_recursive($consumer), true)
                    ? $consumer::withTrashed()
                    : $consumer::query();

                $query->where('status_id', $status->id)->update($changes);
            }
        });
    }
}
