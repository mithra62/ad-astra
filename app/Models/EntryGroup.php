<?php

namespace App\Models;

use App\Traits\Field\HasFieldGroups;
use App\Traits\Field\HasFieldLayout;
use App\Traits\HasCategoryGroups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class EntryGroup extends Model
{
    use HasCategoryGroups, HasFactory, HasFieldGroups, HasFieldLayout;

    protected $fillable = [
        'field_layout_id',
        'status_group_id',
        'name',
        'handle',
        'description',
        'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function statusGroup(): BelongsTo
    {
        return $this->belongsTo(StatusGroup::class);
    }

    public function statuses(): HasManyThrough
    {
        return $this->hasManyThrough(Status::class, StatusGroup::class, 'id', 'status_group_id', 'status_group_id', 'id');
    }

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
