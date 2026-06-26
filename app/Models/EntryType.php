<?php

namespace App\Models;

use App\Traits\Field\HasFieldLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntryType extends Model
{
    use HasFactory, HasFieldLayout;

    protected $fillable = [
        'entry_group_id',
        'entry_behavior_id',
        'field_layout_id',
        'name',
        'handle',
        'default_template',
        'has_entry_tree',
        'max_depth',
        'allowed_parent_types',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'has_entry_tree' => 'boolean',
        'allowed_parent_types' => 'array',
    ];

    public function entryGroup(): BelongsTo
    {
        return $this->belongsTo(EntryGroup::class);
    }

    public function entryBehavior(): BelongsTo
    {
        return $this->belongsTo(EntryBehavior::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function scopeInGroup(Builder $query, int|EntryGroup $group): Builder
    {
        $id = $group instanceof EntryGroup ? $group->getKey() : $group;
        return $query->where('entry_group_id', $id);
    }
}
