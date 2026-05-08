<?php

namespace App\Traits;

use App\Models\Media;
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
     * @var array<string, \App\Models\Media|null>
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
     * Media attached via a specific FileUpload field.
     * Pass int ID in batch contexts; pass string handle in single-model contexts.
     * once() memoises the handle→ID lookup within one request lifecycle.
     */
    public function mediaForField(string|int $field): MorphToMany
    {
        $fieldId = is_int($field)
            ? $field
            : once(fn () => \App\Models\Field::where('handle', $field)->value('id'));

        return $this->morphToMany(Media::class, 'mediable', 'mediables')
                    ->wherePivot('field_id', $fieldId)
                    ->withTimestamps()
                    ->withPivot('sort_order', 'field_id')
                    ->orderByPivot('sort_order');
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

    /** Replace direct attachments with exactly the given IDs. */
    public function syncMedia(array $mediaIds): void
    {
        $this->directMedia()->sync($mediaIds);
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
                ->when($libraryHandle, fn ($q) => $q->whereHas(
                    'library', fn ($lq) => $lq->where('handle', $libraryHandle)
                ))
                ->first();
        }

        return $this->firstMediaCache[$key];
    }
}
