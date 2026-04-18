<?php

namespace App\Models;

use App\Repositories\EntryRepository;
use App\Traits\Category\HasCategories;
use App\Traits\Fieldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    use Fieldable, HasCategories;

    protected $fillable = [
        'entry_group_id',
        'entry_type_id',
        'created_by_user_id',
        'title',
        'slug',
        'published_at',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function entryGroup(): BelongsTo
    {
        return $this->belongsTo(EntryGroup::class);
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(EntryType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'entry_authors')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    public function entryStatuses(): HasMany
    {
        return $this->hasMany(EntryStatus::class);
    }

    public function statusFor(StatusGroup|int $group): ?Status
    {
        $groupId = $group instanceof StatusGroup ? $group->getKey() : $group;

        return $this->entryStatuses
            ->first(fn($es) => $es->status_group_id === $groupId)
            ?->status;
    }

    public function getFieldLayout(): FieldLayout
    {
        $typeLayout = $this->entryType?->fieldLayout;
        $groupLayout = $this->entryGroup?->fieldLayout;

        return $typeLayout ?? $groupLayout;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeInGroup(Builder $query, string|int|EntryGroup $group): Builder
    {
        if ($group instanceof EntryGroup) {
            return $query->where('entry_group_id', $group->getKey());
        }

        if (is_string($group)) {
            return $query->whereHas('entryGroup', fn($q) => $q->where('handle', $group));
        }

        return $query->where('entry_group_id', $group);
    }

    public function scopeOfType(Builder $query, string|int|EntryType $type): Builder
    {
        if ($type instanceof EntryType) {
            return $query->where('entry_type_id', $type->getKey());
        }

        if (is_string($type)) {
            return $query->whereHas('entryType', fn($q) => $q->where('handle', $type));
        }

        return $query->where('entry_type_id', $type);
    }

    /**
     * Fluent update — applies data through EntryRepository and returns $this.
     * Note: returns static instead of bool (diverges from Eloquent default).
     */
    public function update(array $data = [], array $options = []): static
    {
        app(EntryRepository::class)->applyData($this, $data);

        return $this;
    }

    /**
     * Delete through EntryRepository.
     */
    public function delete(): bool
    {
        return app(EntryRepository::class)->delete($this);
    }
}
