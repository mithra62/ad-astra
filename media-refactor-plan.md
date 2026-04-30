# Media Layer Refactor Plan

## Overview

Replace Spatie's individual Media item handling with a fully native Laravel implementation,
bringing the Media layer in line with the Category/FieldGroup pattern already established in
this codebase. The Media Library remains the central authority for all file assets system-wide
— user avatars, file upload fields, downloads, and anything else that touches the filesystem.

---

## Current State

- `Media` model extends Spatie's `BaseMedia` — tightly coupled to Spatie internals
- `Media\Library` implements Spatie's `HasMedia` / `InteractsWithMedia` — upload pipeline
  goes through Spatie
- The `media` table carries Spatie-specific columns (`manipulations`, `generated_conversions`,
  `responsive_images`, `conversions_disk`) with no equivalent use in this system
- Upload action calls `$library->addMedia()->toMediaCollection()` — entirely Spatie API
- `Media` model uses `spatie/laravel-tags` (`HasTags`) — a second Spatie dependency
- User avatars are Gravatar only; no file-based avatar path exists
- No `FileUpload` field type exists yet

---

## Confirmed Design Decisions

| Decision | Detail |
|---|---|
| Image transformations | Hand-rolled. Driver interface stubbed; library TBD |
| Soft deletes | Yes — `Media` soft-deletes; physical file purge runs on a schedule |
| Model usage tracking | `mediables` pivot table (MorphToMany), not a column on `media` |

---

## Target Architecture

### Guiding Principle

Follow the exact same structural pattern that `EntryGroup` uses, applied to the Media layer:

```
MediaLibrary
  ├── HasFieldLayout        (field_layout_id FK → FieldLayout)
  ├── HasFieldGroups        (MorphToMany → FieldGroup via field_groupables)
  ├── HasCategoryGroups     (MorphToMany → Category\Group via category_groupables)
  └── HasMediaItems (new)   (HasMany → Media, drives upload/soft-delete logic)

Media
  ├── SoftDeletes           (deleted_at; physical purge is a scheduled job)
  ├── Fieldable             (MorphMany → FieldValue, custom meta per item)
  ├── HasCategories         (MorphToMany → Category via categorizables)
  ├── HasTransformations    (HasMany → MediaTransformation; driver interface stubbed)
  ├── usages()              (MorphToMany back through mediables — "who uses me?")
  └── BelongsTo MediaLibrary

Any model that holds media (User, Entry, …)
  └── HasMedia trait        (MorphToMany → Media via mediables pivot)
```

All file I/O goes through Laravel's `Storage::disk()`. All transformation I/O goes through
a driver interface whose concrete implementation is chosen later.

---

## Phase 1 — Schema

### 1.1 Rewrite the `media` table

Drop all Spatie-specific columns. Add soft deletes and first-class fields.

**`media` table — final columns:**

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | PK |
| `uuid` | uuid | nullable, unique — stable public identifier |
| `library_id` | foreignId | FK → media_libraries, nullOnDelete |
| `name` | string | human label |
| `file_name` | string | stored filename on disk (UUID-based) |
| `original_name` | string | filename at upload time |
| `mime_type` | string | nullable |
| `disk` | string | Laravel disk name, mirrored from library at upload time |
| `path` | string | relative path within the disk |
| `size` | unsignedBigInteger | bytes |
| `alt_text` | string | nullable — accessibility |
| `title` | string | nullable |
| `sort_order` | unsignedInteger | default 0 |
| `deleted_at` | timestamp | nullable — soft delete |
| `timestamps` | | |

**Remove from current schema:** `model_type`, `model_id`, `collection_name`,
`conversions_disk`, `manipulations`, `generated_conversions`, `responsive_images`,
`order_column`, `custom_properties`

> **Why no `mediable` columns on `media`?** A single file can legitimately be referenced
> by many models (an image used in two entries, a PDF linked from a user profile and a
> download field). Storing one `mediable_type/id` on the row would mean the second reference
> overwrites the first. The `mediables` pivot table below handles arbitrary many-to-many
> ownership cleanly.

### 1.2 New `mediables` pivot table

```php
Schema::create('mediables', function (Blueprint $table) {
    $table->id();
    $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
    $table->morphs('mediable');          // mediable_type, mediable_id
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->unique(['media_id', 'mediable_type', 'mediable_id'], 'mediables_unique');
    $table->index(['mediable_type', 'mediable_id']);
});
```

This is the same pattern as `categorizables`, `field_groupables`, and `category_groupables`.

### 1.3 New `media_transformations` table

```php
Schema::create('media_transformations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();

    $table->string('key');              // e.g. 'thumbnail', 'hero', 'avatar_sm'
    $table->string('disk');             // same disk as parent media
    $table->string('path');             // relative path to the derived file
    $table->string('mime_type')->nullable();
    $table->unsignedBigInteger('size')->nullable();
    $table->unsignedInteger('width')->nullable();
    $table->unsignedInteger('height')->nullable();

    // Driver params used to produce this transformation (for cache-busting / re-generation)
    $table->json('params')->nullable(); // width, height, format, quality, fit mode, etc.
    $table->string('driver')->nullable(); // populated once a library is chosen

    // Generation lifecycle
    $table->string('status')->default('pending'); // pending | complete | failed
    $table->text('error')->nullable();            // failure message if status = failed

    $table->timestamps();
    $table->unique(['media_id', 'key']);
});
```

### 1.4 Alter `media_libraries` table

```php
Schema::table('media_libraries', function (Blueprint $table) {
    $table->foreignId('field_layout_id')
        ->nullable()
        ->after('handle')
        ->constrained('field_layouts')
        ->nullOnDelete();
});
```

---

## Phase 2 — Models

### 2.1 `App\Models\Media` (full rewrite)

```php
namespace App\Models;

use App\Traits\Fieldable;
use App\Traits\Category\HasCategories;
use App\Traits\HasTransformations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use Fieldable, HasCategories, HasTransformations, SoftDeletes;

    protected $fillable = [
        'library_id', 'name', 'file_name', 'original_name',
        'mime_type', 'disk', 'path', 'size',
        'alt_text', 'title', 'sort_order',
    ];

    protected $casts = [
        'size'       => 'integer',
        'sort_order' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class, 'library_id');
    }

    /**
     * All models that reference this media item.
     * Inverse of the HasMedia trait's media() relationship.
     */
    public function usages(): MorphToMany
    {
        // Because this is a self-referencing morph pivot there's no single
        // "related" type — callers query usages()->get() and inspect
        // mediable_type/mediable_id on the pivot rows directly.
        // See MediaUsagesCollection note below.
        return $this->morphedByMany(Media::class, 'mediable', 'mediables', 'media_id')
                    ->withTimestamps()
                    ->withPivot('sort_order', 'mediable_type', 'mediable_id');
    }

    public function transformations(): HasMany
    {
        return $this->hasMany(Media\Transformation::class);
    }

    // ── Storage helpers ────────────────────────────────────────────

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function temporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($minutes)
        );
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }
}
```

> **On `usages()`:** Eloquent's `morphedByMany` expects a single related model class, which
> doesn't work cleanly when the pivot can point to *any* model type. In practice, callers
> should use raw pivot queries or a dedicated `MediaUsageRepository::forMedia(Media $m)`
> method that groups rows by `mediable_type` and eager-loads each type separately. The
> `usages()` relationship above is a reasonable starting stub — refine it when the usage
> query UI is built.

### 2.2 `App\Models\Media\Transformation`

```php
namespace App\Models\Media;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Transformation extends Model
{
    protected $table = 'media_transformations';

    protected $fillable = [
        'media_id', 'key', 'disk', 'path', 'mime_type',
        'size', 'width', 'height', 'params', 'driver', 'status', 'error',
    ];

    protected $casts = [
        'params'  => 'array',
        'size'    => 'integer',
        'width'   => 'integer',
        'height'  => 'integer',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isComplete(): bool { return $this->status === 'complete'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }

    public function markComplete(string $path, int $size, int $width = null, int $height = null): void
    {
        $this->update(compact('path', 'size', 'width', 'height') + ['status' => 'complete', 'error' => null]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }
}
```

### 2.3 `App\Models\Media\Library` (refactor)

```php
namespace App\Models\Media;

use App\Traits\HasCategoryGroups;
use App\Traits\HasFieldGroups;
use App\Traits\HasFieldLayout;
use App\Traits\HasMediaItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Library extends Model
{
    use HasCategoryGroups, HasFieldGroups, HasFieldLayout, HasMediaItems;

    protected $fillable = [
        'field_layout_id', 'name', 'handle', 'adapter',
        'adapter_settings', 'allowed_types', 'max_size', 'sort_order',
    ];

    protected $casts = [
        'sort_order'      => 'integer',
        'adapter_settings'=> 'array',
        'allowed_types'   => 'array',
        'max_size'        => 'integer',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(\App\Models\Media::class, 'library_id')
                    ->orderBy('sort_order');
    }

    /**
     * Active (non-deleted) media only.
     */
    public function activeMedia(): HasMany
    {
        return $this->media()->whereNull('deleted_at');
    }
}
```

---

## Phase 3 — Traits

### 3.1 `App\Traits\HasMediaItems`

Lives on `Media\Library`. Drives upload and soft-delete. Physical file removal is
intentionally **not** done here — that belongs to the purge job, preserving data
safety on accidental deletes.

```php
namespace App\Traits;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasMediaItems
{
    /**
     * Store an uploaded file and create a Media record.
     * Validates against library constraints before touching storage.
     *
     * @throws \InvalidArgumentException on constraint violation
     */
    public function addMediaFromUpload(UploadedFile $file, array $attributes = []): Media
    {
        $errors = $this->validateUpload($file);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $disk     = $this->adapter;
        $folder   = $this->handle;
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs($folder, $fileName, $disk);

        return $this->media()->create(array_merge([
            'uuid'          => (string) Str::uuid(),
            'name'          => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name'     => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'disk'          => $disk,
            'path'          => $path,
            'size'          => $file->getSize(),
            'sort_order'    => (int) ($this->media()->max('sort_order') ?? 0) + 1,
        ], $attributes));
    }

    /**
     * Soft-delete the Media record.
     * The physical file is NOT removed here — the PurgeDeletedMedia job handles that
     * after the grace period, preventing data loss from accidental deletes.
     */
    public function removeMedia(Media $media): void
    {
        $media->delete(); // SoftDeletes — sets deleted_at
    }

    /**
     * Permanently delete a Media record and its physical file.
     * Called by the purge job, or explicitly when you are certain.
     */
    public function purgeMedia(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);

        // Also remove any transformation files
        foreach ($media->transformations as $transformation) {
            Storage::disk($transformation->disk)->delete($transformation->path);
        }

        $media->forceDelete();
    }

    /**
     * Validate an upload against this library's constraints.
     * Returns an array of human-readable error strings; empty = valid.
     */
    public function validateUpload(UploadedFile $file): array
    {
        $errors = [];

        if ($this->max_size && $file->getSize() > ($this->max_size * 1024 * 1024)) {
            $errors[] = "File exceeds the maximum allowed size of {$this->max_size} MB.";
        }

        if (!empty($this->allowed_types) && !in_array($file->getMimeType(), $this->allowed_types, true)) {
            $errors[] = "File type '{$file->getMimeType()}' is not allowed in this library.";
        }

        return $errors;
    }
}
```

### 3.2 `App\Traits\HasMedia`

Goes on any model that needs to hold media (User, Entry, etc.). Uses the `mediables`
pivot — many-to-many, so the same file can be attached to multiple models.

```php
namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasMedia
{
    /**
     * All media attached to this model via the mediables pivot.
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
                    ->withTimestamps()
                    ->withPivot('sort_order')
                    ->orderByPivot('sort_order');
    }

    /**
     * Attach a Media item to this model.
     */
    public function attachMedia(Media $media, int $sortOrder = 0): void
    {
        $this->media()->syncWithoutDetaching([
            $media->id => ['sort_order' => $sortOrder],
        ]);
    }

    /**
     * Detach a Media item from this model.
     * Does NOT delete or soft-delete the media record itself.
     */
    public function detachMedia(Media $media): void
    {
        $this->media()->detach($media->id);
    }

    /**
     * Replace all attached media with the given IDs.
     * Accepts [media_id => ['sort_order' => n], …] or a flat array of IDs.
     */
    public function syncMedia(array $mediaIds): void
    {
        $this->media()->sync($mediaIds);
    }

    /**
     * First attached media item, optionally scoped to a library handle.
     */
    public function firstMedia(string $libraryHandle = null): ?Media
    {
        return $this->media()
            ->when($libraryHandle, fn($q) => $q->whereHas(
                'library', fn($lq) => $lq->where('handle', $libraryHandle)
            ))
            ->first();
    }
}
```

### 3.3 `App\Traits\HasTransformations`

Lives on the `Media` model. Stubs the transformation interface without binding to
any specific library.

```php
namespace App\Traits;

use App\Models\Media\Transformation;
use App\Services\Media\TransformationDriverInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTransformations
{
    /**
     * Retrieve an existing transformation by key, or return null.
     */
    public function getTransformation(string $key): ?Transformation
    {
        return $this->transformations()->where('key', $key)->first();
    }

    /**
     * Return a transformation if it exists and is complete; otherwise null.
     */
    public function transformation(string $key): ?Transformation
    {
        $t = $this->getTransformation($key);
        return ($t && $t->isComplete()) ? $t : null;
    }

    /**
     * Check whether a completed transformation exists for the given key.
     */
    public function hasTransformation(string $key): bool
    {
        return $this->transformation($key) !== null;
    }

    /**
     * Request a transformation. Creates a pending record if one does not already
     * exist, then dispatches generation via the driver.
     *
     * @param  string  $key     Stable identifier (e.g. 'thumbnail', 'hero_2x')
     * @param  array   $params  Driver-agnostic params — see TransformationParams
     */
    public function transform(string $key, array $params = []): Transformation
    {
        $existing = $this->getTransformation($key);

        if ($existing && $existing->isComplete()) {
            return $existing;
        }

        $transformation = $existing ?? $this->transformations()->create([
            'key'    => $key,
            'disk'   => $this->disk,
            'path'   => $this->derivedPath($key, $params),
            'params' => $params,
            'status' => 'pending',
        ]);

        // Dispatch to the driver — concrete implementation wired in later
        app(TransformationDriverInterface::class)->dispatch($transformation);

        return $transformation;
    }

    /**
     * Delete a specific transformation's file and record.
     */
    public function clearTransformation(string $key): void
    {
        $t = $this->getTransformation($key);
        if (!$t) return;

        if ($t->fileExists()) {
            \Illuminate\Support\Facades\Storage::disk($t->disk)->delete($t->path);
        }

        $t->delete();
    }

    /**
     * Delete all transformation files and records for this media item.
     */
    public function clearTransformations(): void
    {
        foreach ($this->transformations as $t) {
            if ($t->fileExists()) {
                \Illuminate\Support\Facades\Storage::disk($t->disk)->delete($t->path);
            }
            $t->delete();
        }
    }

    /**
     * Derive a deterministic storage path for a transformation.
     * Keeps derived files co-located with the source for easy cleanup.
     *
     * Example: images/photo.jpg + key=thumbnail → images/_t/photo_thumbnail.jpg
     */
    protected function derivedPath(string $key, array $params = []): string
    {
        $dir      = dirname($this->path);
        $stem     = pathinfo($this->file_name, PATHINFO_FILENAME);
        $ext      = $params['format'] ?? pathinfo($this->file_name, PATHINFO_EXTENSION);
        return $dir . '/_t/' . $stem . '_' . $key . '.' . $ext;
    }
}
```

---

## Phase 4 — Transformation Driver Interface

This is the stub contract. Swap in any image library behind it without touching the
rest of the codebase.

### 4.1 `App\Services\Media\TransformationDriverInterface`

```php
namespace App\Services\Media;

use App\Models\Media\Transformation;

interface TransformationDriverInterface
{
    /**
     * Apply the transformation params and write the derived file to disk.
     * Must call $transformation->markComplete() or $transformation->markFailed().
     */
    public function dispatch(Transformation $transformation): void;

    /**
     * Apply params synchronously and return the resulting file path on the disk.
     * Used when you need the transformed file immediately (e.g. during a request).
     */
    public function applySync(Transformation $transformation): string;

    /**
     * Resize to exact dimensions.
     * @stub — concrete driver implements
     */
    public function resize(int $width, int $height): static;

    /**
     * Resize to fit within a bounding box, preserving aspect ratio.
     * @stub — concrete driver implements
     */
    public function fit(int $width, int $height): static;

    /**
     * Crop to exact dimensions from the given origin.
     * @stub — concrete driver implements
     */
    public function crop(int $width, int $height, int $x = 0, int $y = 0): static;

    /**
     * Output quality (1–100). Applies to JPEG and WebP.
     * @stub — concrete driver implements
     */
    public function quality(int $quality): static;

    /**
     * Convert to a different image format.
     * Accepted: 'jpg', 'png', 'webp', 'gif', 'avif'
     * @stub — concrete driver implements
     */
    public function format(string $format): static;

    /**
     * Sharpen the image after transformation.
     * @stub — concrete driver implements
     */
    public function sharpen(int $amount = 10): static;

    /**
     * Apply a watermark from another media item or path.
     * @stub — concrete driver implements
     */
    public function watermark(string $sourcePath, string $position = 'bottom-right'): static;
}
```

### 4.2 `App\Services\Media\NullTransformationDriver` (placeholder)

Binds immediately so the system doesn't blow up before a real driver is wired in.

```php
namespace App\Services\Media;

use App\Models\Media\Transformation;

class NullTransformationDriver implements TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void
    {
        $transformation->markFailed('No transformation driver configured.');
    }

    public function applySync(Transformation $transformation): string
    {
        throw new \RuntimeException('No transformation driver configured.');
    }

    public function resize(int $width, int $height): static { return $this; }
    public function fit(int $width, int $height): static    { return $this; }
    public function crop(int $width, int $height, int $x = 0, int $y = 0): static { return $this; }
    public function quality(int $quality): static           { return $this; }
    public function format(string $format): static          { return $this; }
    public function sharpen(int $amount = 10): static       { return $this; }
    public function watermark(string $sourcePath, string $position = 'bottom-right'): static { return $this; }
}
```

Register in `AppServiceProvider`:

```php
$this->app->bind(
    \App\Services\Media\TransformationDriverInterface::class,
    \App\Services\Media\NullTransformationDriver::class
);
```

When the real library is chosen, swap just this binding — everything else stays the same.

---

## Phase 5 — MediaStorageService

Centralises validation, upload, delete, and URL resolution so controllers and actions
never call Storage directly.

```php
namespace App\Services;

use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    /**
     * Validate and store an uploaded file into the given library.
     *
     * @throws \InvalidArgumentException on constraint violation
     */
    public function upload(Library $library, UploadedFile $file, array $attributes = []): Media
    {
        return $library->addMediaFromUpload($file, $attributes);
    }

    /**
     * Soft-delete a media record (physical file preserved until purge).
     */
    public function delete(Media $media): void
    {
        $media->library->removeMedia($media);
    }

    /**
     * Permanently delete a media record and its physical file.
     * Use sparingly — prefer soft-delete and let the purge job handle cleanup.
     */
    public function purge(Media $media): void
    {
        $media->library->purgeMedia($media);
    }

    /**
     * Return a public or time-limited signed URL.
     */
    public function url(Media $media, int $signedMinutes = null): string
    {
        if ($signedMinutes !== null) {
            return $media->temporaryUrl($signedMinutes);
        }
        return $media->url();
    }

    /**
     * Return the underlying Storage disk instance for a media item.
     */
    public function disk(Media $media)
    {
        return Storage::disk($media->disk);
    }
}
```

Register in `AppServiceProvider`:

```php
$this->app->singleton('media-service', fn() => new \App\Services\MediaStorageService());
```

---

## Phase 6 — Soft Delete Purge Job

`App\Jobs\PurgeDeletedMedia` runs on a schedule and permanently removes files and records
that have been soft-deleted beyond a configurable grace period.

```php
namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

class PurgeDeletedMedia implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        protected int $graceDays = 30
    ) {}

    public function handle(): void
    {
        Media::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($this->graceDays))
            ->with('transformations')
            ->chunkById(100, function ($items) {
                foreach ($items as $media) {
                    // Remove transformation files first
                    foreach ($media->transformations as $t) {
                        if (Storage::disk($t->disk)->exists($t->path)) {
                            Storage::disk($t->disk)->delete($t->path);
                        }
                    }

                    // Remove source file
                    if (Storage::disk($media->disk)->exists($media->path)) {
                        Storage::disk($media->disk)->delete($media->path);
                    }

                    $media->forceDelete();
                }
            });
    }
}
```

Schedule in `routes/console.php` (Laravel 11+) or `Kernel.php`:

```php
Schedule::job(new PurgeDeletedMedia(graceDays: 30))->daily();
```

The grace period is configurable — 30 days is a safe default. Set to 0 to purge
immediately (not recommended for production).

---

## Phase 7 — FileUpload Field Type

```php
namespace App\Field\Types;

use App\Field\AbstractField;
use App\Models\Media;
use Illuminate\Support\Collection;

class FileUpload extends AbstractField
{
    protected string $handle = 'file_upload';
    protected string $name   = 'File Upload';

    /**
     * Store an array of media IDs as JSON.
     */
    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function cast(mixed $value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }
        return (array) ($value ?? []);
    }

    public function validate(mixed $value): bool|string
    {
        $ids = (array) $value;
        $min = $this->getSetting('min', 0);
        $max = $this->getSetting('max', null);

        if ($min && count($ids) < $min) {
            return "At least {$min} file(s) must be selected.";
        }

        if ($max && count($ids) > $max) {
            return "A maximum of {$max} file(s) may be selected.";
        }

        return true;
    }

    /**
     * Resolve stored IDs to Media model instances, preserving stored order.
     */
    public function resolve(mixed $value): Collection
    {
        $ids = $this->cast($value);
        if (empty($ids)) {
            return collect();
        }

        // Preserve the stored sort order
        return Media::whereIn('id', $ids)
            ->get()
            ->sortBy(fn($m) => array_search($m->id, $ids))
            ->values();
    }
}
```

**Field settings stored in the `fields` row:**

| Key | Default | Purpose |
|---|---|---|
| `library_id` | null | Scope picker to a specific library |
| `library_handle` | null | Alternative to `library_id`; portable across environments |
| `min` | 0 | Minimum selections required |
| `max` | null | Maximum selections (null = unlimited) |
| `allowed_types` | null | Override library MIME types at field level |

---

## Phase 8 — User Avatars

### 8.1 System library seeder

```php
use App\Models\Media\Library as MediaLibrary;

MediaLibrary::firstOrCreate(['handle' => 'avatars'], [
    'name'          => 'User Avatars',
    'adapter'       => config('filesystems.default'),
    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    'max_size'      => 2,
    'sort_order'    => 0,
]);
```

### 8.2 `User` model update

```php
use App\Traits\HasMedia;

class User extends Authenticatable
{
    use HasMedia;

    /**
     * Return the user's avatar URL.
     * Checks the avatars library first; falls back to Gravatar.
     */
    public function avatar(): string
    {
        $media = $this->firstMedia('avatars');

        if ($media) {
            return $media->url();
        }

        return \Laravolt\Avatar\Facade::create($this->name)->toBase64();
    }

    /**
     * Set a new avatar, replacing any existing one.
     */
    public function setAvatar(Media $media): void
    {
        // Detach existing avatar media from this user
        $existing = $this->media()
            ->whereHas('library', fn($q) => $q->where('handle', 'avatars'))
            ->get();

        foreach ($existing as $old) {
            $this->detachMedia($old);
        }

        $this->attachMedia($media);
    }
}
```

---

## Phase 9 — Refactor Existing Actions

### 9.1 `UploadMedia` action

```php
public function upload(FormRequest $request, Library $library): Media
{
    $media = app('media-service')->upload($library, $request->file('file'), [
        'name' => $request->input('name'),
    ]);

    if (!empty($request->input('categories'))) {
        $media->categories()->sync($request->input('categories'));
    }

    return $media;
}
```

### 9.2 `DeleteMedia` action

```php
public function delete(Media $media): void
{
    app('media-service')->delete($media); // soft delete
}
```

### 9.3 `ProcessMediaLibraryRemoval` job

Update to dispatch `PurgeDeletedMedia` or call `purge()` directly on each media item.
Remove all Spatie calls.

---

## Phase 10 — Remove Spatie

Once all models, traits, actions, and controllers are updated and tests pass:

```bash
composer remove spatie/laravel-medialibrary spatie/laravel-tags
```

- Remove any explicit provider registration from `config/app.php`
- Delete or repurpose `config/media-library.php`
- Remove the `HasTags` import anywhere it still appears
- Confirm the morph map in `AppServiceProvider` still has `'media'` and `'media_library'`

---

## Implementation Order

1. **Schema** — `media` table rewrite, `mediables` table, `media_transformations` table, alter `media_libraries`
2. **Models** — `Media`, `Media\Transformation`, `Media\Library`
3. **Traits** — `HasMediaItems`, `HasMedia`, `HasTransformations`
4. **Driver interface** — `TransformationDriverInterface`, `NullTransformationDriver`, AppServiceProvider binding
5. **Service** — `MediaStorageService`, register binding
6. **Purge job** — `PurgeDeletedMedia`, schedule it
7. **Field type** — `FileUpload`
8. **Actions/Controllers** — `UploadMedia`, `DeleteMedia`, `ProcessMediaLibraryRemoval`
9. **User avatars** — seeder, `User` model update
10. **Tests** — `Storage::fake()` for upload/delete/purge; `NullTransformationDriver` for transformation stubs
11. **Remove Spatie** — `composer remove`, config cleanup

---

## What Stays the Same

- `media_libraries` table structure (add `field_layout_id` only)
- `HasFieldGroups` / `HasCategoryGroups` / `HasFieldLayout` traits on Library
- `FilesService` MIME type registry — still used for `allowed_types` validation
- All routes and controller signatures — only internals change
- Morph map keys in `AppServiceProvider` (`'media'`, `'media_library'`)
- The `categories()` relationship concept on `Media` — just moves to `HasCategories` trait

---

## Notes for When the Image Library Is Chosen

The only file that changes is the concrete driver class wired into the AppServiceProvider
binding. The interface contract above covers the common operations across Intervention Image
(v3), Imagick, Gumlet, and others. When you pick one:

1. Create `App\Services\Media\{LibraryName}TransformationDriver implements TransformationDriverInterface`
2. Swap the binding: `$this->app->bind(TransformationDriverInterface::class, YourDriver::class)`
3. Implement `dispatch()` to call the library and write to `Storage::disk()` — the path
   is already computed and stored on the `Transformation` record
4. Done — zero changes to models, traits, or callers
