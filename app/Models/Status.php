<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_group_id',
        'name',
        'handle',
        'color',
        'is_default',
        'is_public',
        'sort_order'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(StatusGroup::class, 'status_group_id');
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeInGroup(Builder $query, int|StatusGroup $group): Builder
    {
        $id = $group instanceof StatusGroup ? $group->getKey() : $group;

        return $query->where('status_group_id', $id);
    }
}
