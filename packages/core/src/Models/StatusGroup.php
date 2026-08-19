<?php

namespace AdAstra\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StatusGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'handle',
        'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer'
    ];

    /**
     * @return HasMany<Status, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<EntryGroup, $this>
     */
    public function entryGroups(): HasMany
    {
        return $this->hasMany(EntryGroup::class);
    }

    /**
     * @return HasMany<Media\Library, $this>
     */
    public function mediaLibraries(): HasMany
    {
        return $this->hasMany(Media\Library::class);
    }

    /**
     * @return HasOne<Status, $this>
     */
    public function defaultStatus(): HasOne
    {
        return $this->hasOne(Status::class)->where('is_default', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
