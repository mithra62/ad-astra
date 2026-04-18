<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryStatus extends Model
{
    protected $table = 'entry_statuses';

    protected $fillable = ['entry_id', 'status_group_id', 'status_id'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function statusGroup(): BelongsTo
    {
        return $this->belongsTo(StatusGroup::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
