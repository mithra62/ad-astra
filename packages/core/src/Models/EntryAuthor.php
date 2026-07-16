<?php

namespace AdAstra\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EntryAuthor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_author_entry')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('status', 'disabled');
    }

    /**
     * Return the display name, falling back to the related user's name.
     * Safe to call without eager-loading `user` only when display_name is set.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name']
            ?? $this->user?->name
            ?? '';
    }
}
