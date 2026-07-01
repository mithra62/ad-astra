<?php

namespace AdAstra\Traits;

use AdAstra\Models\Field;
use AdAstra\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasMedia
{
    // field_id sentinel: 0 = direct attachment (avatar, library browser pick).
    //                    N = attached through a specific FileUpload field.
    // NULL is intentionally avoided — most SQL engines permit multiple NULLs in
    // a unique index, so nullable field_id would not protect against duplicate
    // direct attachments at the DB level. See mediables migration for details.
    private const DIRECT_ATTACHMENT = 0;

    /**
     * Per-instance cache for firstMedia() results.
     *
     * Keyed by library handle string (empty string = no handle filter).
     * Cleared automatically whenever a mutation method changes the pivot rows,
     * so reads within the same request never see stale data.
     *
     * @var array<string, Media|null>
     */
    private array $firstMediaCache = [];

    /** All media attached to this model via any method. */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->withTimestamps()
            ->withPivot('sort_order', 'field_id')
            ->orderByPivot('sort_order');
    }

    /** Media attached directly (field_id = 0 sentinel). */
    public function directMedia(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->wherePivot('field_id', self::DIRECT_ATTACHMENT)
            ->withTimestamps()
            ->withPivot('sort_order', 'field_id')
            ->orderByPivot('sort_order');
    }

    /**
     * Process-wide handle→ID cache shared across all instances and requests.
     * Static so the lookup survives across multiple model instances in one process.
     *
     * @var array<string, int|null>
     */
    private static array $fieldHandleCache = [];

    /**
     * Media attached via a specific FileUpload field.
     * Pass int ID in batch contexts; pass string handle in single-model contexts.
     * Handle→ID lookups are cached in a static array keyed by handle string,
     * so repeated calls with the same handle only hit the DB once per process.
     */
    public function mediaForField(string|int $field): MorphToMany
    {
        $fieldId = is_int($field)
            ? $field
            : $this->resolveFieldHandle($field);

        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->wherePivot('field_id', $fieldId)
            ->withTimestamps()
            ->withPivot('sort_order', 'field_id')
            ->orderByPivot('sort_order');
    }

    private function resolveFieldHandle(string $handle): ?int
    {
        if (!array_key_exists($handle, static::$fieldHandleCache)) {
            static::$fieldHandleCache[$handle] = Field::where('handle', $handle)->value('id');
        }

        return static::$fieldHandleCache[$handle];
    }

    /** Attach a media item as a direct attachment. Idempotent. */
    public function attachMedia(Media $media, int $sortOrder = 0): void
    {
        $this->directMedia()->syncWithoutDetaching([
            $media->id => ['sort_order' => $sortOrder, 'field_id' => self::DIRECT_ATTACHMENT],
        ]);
        $this->firstMediaCache = [];
    }

    /** Remove a media item from all pivot rows for this model. */
    public function detachMedia(Media $media): void
    {
        $this->media()->detach($media->id);
        $this->firstMediaCache = [];
    }

    /**
     * Replace direct attachments with exactly the given IDs.
     *
     * Accepts a flat array of IDs; sequential sort_order (0-based) is assigned
     * automatically so the caller's order is preserved in the pivot table.
     * field_id is always written as the DIRECT_ATTACHMENT sentinel (0).
     */
    public function syncMedia(array $mediaIds): void
    {
        $pivot = [];
        foreach (array_values($mediaIds) as $i => $id) {
            $pivot[$id] = ['sort_order' => $i, 'field_id' => self::DIRECT_ATTACHMENT];
        }
        $this->directMedia()->sync($pivot);
        $this->firstMediaCache = [];
    }

    /**
     * First directly-attached item, optionally scoped to a library handle.
     *
     * Results are cached on the model instance for the duration of the request.
     * Calling attachMedia / detachMedia / syncMedia clears the cache automatically.
     * If you need to bypass the cache (e.g. after a raw pivot mutation), call
     * $model->firstMediaCache = [] or unset($model->firstMediaCache[$handle]).
     */
    public function firstMedia(?string $libraryHandle = null): ?Media
    {
        $key = $libraryHandle ?? '';

        if (!array_key_exists($key, $this->firstMediaCache)) {
            $this->firstMediaCache[$key] = $this->directMedia()
                ->when($libraryHandle, fn($q) => $q->whereHas(
                    'library', fn($lq) => $lq->where('handle', $libraryHandle)
                ))
                ->first();
        }

        return $this->firstMediaCache[$key];
    }
}
