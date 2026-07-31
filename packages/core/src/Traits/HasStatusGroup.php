<?php

namespace AdAstra\Traits;

use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Shared status-group ownership for models that point at a StatusGroup
 * via a status_group_id column (currently EntryGroup and Media\Library).
 *
 * Note: defaultStatus() is a METHOD here (returns ?Status), not a relation.
 * Accessing it as a property ($model->defaultStatus) will not work — call
 * $model->defaultStatus() instead. The relation itself lives on StatusGroup.
 */
trait HasStatusGroup
{
    /**
     * @return BelongsTo<StatusGroup, $this>
     */
    public function statusGroup(): BelongsTo
    {
        return $this->belongsTo(StatusGroup::class);
    }

    /**
     * @return HasManyThrough<Status, StatusGroup, $this>
     */
    public function statuses(): HasManyThrough
    {
        return $this->hasManyThrough(Status::class, StatusGroup::class, 'id', 'status_group_id', 'status_group_id', 'id');
    }

    public function defaultStatus(): ?Status
    {
        return $this->statusGroup?->defaultStatus;
    }
}
