<?php

namespace AdAstra\Traits\Field;

use AdAstra\Models\FieldLayout;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasFieldLayout
{
    public static function resolvedFields(int $id): static
    {
        return static::query()
            ->with('fieldLayout.tabs.elements.field')
            ->findOrFail($id);
    }

    /**
     * @return BelongsTo<FieldLayout, $this>
     */
    public function fieldLayout(): BelongsTo
    {
        return $this->belongsTo(FieldLayout::class);
    }
}
