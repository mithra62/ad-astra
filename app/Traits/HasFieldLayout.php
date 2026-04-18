<?php

namespace App\Traits;

use App\Models\FieldLayout;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasFieldLayout
{
    public function fieldLayout(): BelongsTo
    {
        return $this->belongsTo(FieldLayout::class);
    }
}
