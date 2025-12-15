<?php

namespace mithra62\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use mithra62\Shop\Models\Category\Group;

class Category extends Model
{
    protected $fillable = [
        'category_group_id',
        'parent_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'category_group_id' => 'integer',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'category_group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInGroup(Builder $query, int|Group $group): Builder
    {
        $groupId = $group instanceof Group ? $group->getKey() : $group;

        return $query->where('category_group_id', $groupId);
    }
}
