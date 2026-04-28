<?php

namespace App\Models;

use App\Traits\Category\HasCategories;
use App\Traits\Fieldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Entry extends Model
{
    use Fieldable, HasCategories, HasFactory;

    protected $fillable = [
        'entry_group_id',
        'entry_type_id',
        'status_id',
        'status_handle',
        'status_is_public',
        'created_by_user_id',
        'title',
        'handle',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'status_is_public' => 'boolean',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

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

    public function entryRelationships(): HasMany
    {
        return $this->hasMany(EntryRelationship::class)->orderBy('sort_order');
    }

    /**
     * Resolve a field value by handle.
     *
     * For scalar field types this reads from fieldValues (morphMany).
     * For relational field types this reads from entryRelationships and returns
     * a Collection of related Entry models, ordered by sort_order.
     *
     * REQUIRES the following relations to be eager-loaded to avoid N+1 queries:
     *   - fieldValues.field.fieldType
     *   - entryRelationships.field
     *   - entryRelationships.relatedEntry
     *
     * Use EntryService::get() / EntryService::find() which apply the full eager-load, or load them explicitly.
     */
    public function field(string $handle): mixed
    {
        // Scalar field values (text, number, date, etc.)
        $fv = $this->fieldValues->first(fn($v) => $v->field?->handle === $handle);
        if ($fv) {
            return $fv->resolvedValue();
        }

        // Relational field values stored in entry_relationships
        $related = $this->entryRelationships
            ->filter(fn($r) => $r->field?->handle === $handle)
            ->sortBy('sort_order')
            ->pluck('relatedEntry')
            ->filter(); // remove any null entries from broken FKs

        return $related->isNotEmpty() ? $related->values() : null;
    }

    public function getFieldLayout(): ?FieldLayout
    {
        $typeLayout = $this->entryType?->fieldLayout;
        $groupLayout = $this->entryGroup?->fieldLayout;

        return $typeLayout ?? $groupLayout;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status_is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeWithStatus(Builder $query, string $handle): Builder
    {
        return $query->where('status_handle', $handle);
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

    public function entryTree(): HasOne
    {
        return $this->hasOne(EntryTree::class);
    }
}
