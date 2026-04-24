<?php

namespace App\Models;

use App\Models\Category\Group;
use App\Traits\Fieldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use Fieldable, HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'group_id',
        'parent_id',
        'name',
        'handle',
        'sort_order',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function categorizable()
    {
        return $this->morphTo();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
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

    public function childrenRecursive(int $maxDepth = 10): HasMany
    {
        if ($maxDepth <= 0) {
            return $this->children()->whereRaw('0 = 1');
        }

        return $this->children()->with([
            'childrenRecursive' => fn ($q) => $q->childrenRecursive($maxDepth - 1),
        ]);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInGroup(Builder $query, int|Group $group): Builder
    {
        $groupId = $group instanceof Group ? $group->getKey() : $group;

        return $query->where('group_id', $groupId);
    }
}
