<?php

namespace AdAstra\Models;

use AdAstra\Traits\Field\HasFieldLayout;
use AdAstra\Traits\HasCategoryGroups;
use AdAstra\Traits\HasStatusGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntryGroup extends Model
{
    use HasCategoryGroups;
    use HasFactory;
    use HasFieldLayout;
    use HasStatusGroup;

    protected $fillable = [
        'field_layout_id',
        'status_group_id',
        'name',
        'handle',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer'
    ];

    public function entryTypes(): HasMany
    {
        return $this->hasMany(EntryType::class)->orderBy('sort_order');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
