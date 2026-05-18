<?php

namespace App\Listeners;

use App\Events\UserLockChanged;
use App\Events\UserStatusChanged;
use App\Models\UserStatusLog;
use Illuminate\Support\Facades\Auth;

class WriteUserStatusLog
{
    public function handle(object $event): void
    {
        $changedById = Auth::id();

        if ($event instanceof UserStatusChanged) {
            UserStatusLog::create([
                'user_id' => $event->user->id,
                'changed_by_user_id' => $changedById,
                'previous_status' => $event->previousStatus,
                'new_status' => $event->newStatus,
                'reason' => $event->reason,
                'context' => !empty($event->context) ? $event->context : null,
            ]);

            return;
        }

        if ($event instanceof UserLockChanged) {
            UserStatusLog::create([
                'user_id' => $event->user->id,
                'changed_by_user_id' => $changedById,
                'previous_locked_until' => $event->previousLockedUntil,
                'new_locked_until' => $event->newLockedUntil,
                'reason' => $event->reason,
            ]);
        }
    }
}
