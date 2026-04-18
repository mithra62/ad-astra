<?php

namespace App\Models;

use App\Traits\HasCategoryGroups;
use App\Traits\HasFieldGroups;
use App\Traits\HasFieldLayout;
use App\Traits\HasStatusGroups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntryGroup extends Model
{
    use HasFieldLayout, HasFieldGroups, HasCategoryGroups, HasStatusGroups;

    protected $fillable = ['field_layout_id', 'name', 'handle', 'description', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

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
