# Media Layer Refactor Plan

---

## ⚠ Review Notes
*Added 2026-05-01. Re-verified against codebase 2026-05-02. Items marked ✅ are resolved; all others remain open.*

### 🔴 Critical — Bugs / Blockers

**1. `Media\Library` model is missing `$table = 'media_libraries'` in the plan's Phase 2.3 rewrite**
The current `app/Models/Media/Library.php` correctly has `protected $table = 'media_libraries'` at line 27. However, the plan's Phase 2.3 replacement class body omits this property — Eloquent would default to `libraries` and every query would fail. **Do not drop this line when implementing Phase 2.3.**
*Verified 2026-05-02: bug is in the plan code, not the live file. Current codebase is safe.*

**2. `FieldValue::resolvedValue()` is incomplete in the live codebase**
`app/Models/FieldValue.php` ends at line 58, mid-method — the body cuts off after `$column = $fieldType->instance()->storageColumn();` with no return statement and no closing brace. The method has never been able to return a resolved value for any field type. This must be implemented as part of Phase 9, not treated as a pre-existing working baseline.
*Verified 2026-05-02: still incomplete. A prior review incorrectly marked this as resolved — it is not.*

**3. `FieldValue::resolvedValue()` pipeline gap for `FileUpload` (Phase 7 / FileUpload section)**
Because `resolvedValue()` is incomplete (see issue 2), `FileUpload::value()` is never called and `$entry->field('gallery')` cannot return a `Collection<Media>`. When Phase 9 implements this method, it must explicitly call `$instance->value($rawValue)` when the field type exposes a `value()` method, rather than returning the raw column value. The plan's pipeline diagram assumes this call exists — it must be written explicitly.
*Verified 2026-05-02: still unimplemented. A prior review incorrectly marked this as resolved — it is not.*

**4. "File Upload Field Type — Implementation Plan" section is duplicated — three copies exist**
There are three copies of `FileUpload` implementation in this document: (a) Phase 7 (~line 947) — a simple stub using `resolve()` that conflicts with the rest of the plan; (b) the first `# File Upload Field Type — Implementation Plan` section (~line 1179) — the comprehensive canonical version using `value()`; (c) a second `# File Upload Field Type — Implementation Plan` section (~line 1924) — a near-duplicate of (b). Phase 7's `FileUpload` stub and the third copy (~line 1924) must both be removed. The canonical version is the comprehensive one at ~line 1179.
*Verified 2026-05-02: all three copies still present in this document.*

**5. `Media::usages()` relationship is semantically broken (Phase 2.1)**
`morphedByMany(Media::class, 'mediable', 'mediables', 'media_id')` tells Eloquent the related type is always `Media`, so it only returns pivot rows where `mediable_type = 'App\Models\Media'`. All `User`, `Entry`, and other rows are silently dropped. Replace the relationship body with a note pointing directly to `MediaUsageRepository::forMedia()` and do not leave a broken Eloquent relationship in place.
*Verified 2026-05-02: the refactored Media model has not been written. Current `app/Models/Media.php` still extends Spatie's `BaseMedia` with no `usages()` method — the bug is in the plan's proposed code.*

**6. No `DeleteMedia` action exists**
Phase 9.2 references `App\Actions\Media\DeleteMedia`, but only `App\Actions\Media\Library\DeleteMediaLibrary` exists. A new `DeleteMedia` action (for soft-deleting individual media items) must be created as part of Phase 9.
*Verified 2026-05-02: `app/Actions/Media/DeleteMedia.php` still does not exist.*

---

### 🟠 Missing Pieces — Must Be Addressed Before or During Implementation

**7. `User` model uses `spatie/laravel-tags` (`HasTags`) — no replacement specified**
`App\Models\User` (line 20) uses the `HasTags` trait from `spatie/laravel-tags`. `composer.json` requires `spatie/laravel-tags: ^4.10`. Phase 10 removes both Spatie packages, but the plan is silent on what replaces user tagging. Before `composer remove spatie/laravel-tags` runs, a replacement must be identified and the `User` model updated, or the remove command will break the model.
*Verified 2026-05-02: still active. `User.php` lines 16 and 20 still import and use `HasTags`.*

**8. Library deletion does not handle orphaned media**
`DeleteMediaLibrary::delete()` carries a `@todo` about the job queue and simply calls `$library->delete()`. The `media.library_id` FK is `nullOnDelete()` (confirmed in migration `2025_12_27_160903`), so deleting a library sets all child `media.library_id` values to null — those records become orphans the `PurgeDeletedMedia` job will never find or clean up. The plan must specify the deletion policy: (a) cascade-soft-delete all media items first, (b) prohibit deletion if media items exist, or (c) accept orphaned records and add a separate orphan-purge query to the job.
*Verified 2026-05-02: still active. `@todo` comment and bare `$library->delete()` call confirmed.*

**9. `ProcessMediaLibraryRemoval` job is an empty stub**
`app/Jobs/ProcessMediaLibraryRemoval.php` exists but `handle()` contains only a comment and no implementation. Phase 9.3 says to update it but provides no concrete body. At minimum it should call `purgeMedia()` on each media item belonging to the deleted library.
*Verified 2026-05-02: `handle()` is still empty.*

**10. `StoreMediaLibraryFormRequest` uses field name `'storage'`, not `'adapter'`**
`app/Http/Requests/Media/Library/StoreMediaLibraryFormRequest.php` validates a field named `'storage'` (line 34) and reads it via `$this->data('storage')` (line 55), but the `media_libraries` column is `'adapter'`. Phase 9 must update this request (and the create/edit views) to use `'adapter'` so form submissions actually populate the correct column.
*Verified 2026-05-02: still active.*

---

### 🟡 Code-Level Issues — Fix Before Merging

**11. `Transformation::markComplete()` uses non-nullable typed int parameters with null defaults**
`public function markComplete(string $path, int $size, int $width = null, int $height = null)` — a non-nullable `int` cannot default to `null`. This is a PHP fatal error at call time. Change to `?int $width = null, ?int $height = null`.
*Verified 2026-05-02: `app/Models/Media/Transformation.php` does not exist yet — bug is in the plan's Phase 2.2 code. Fix before writing the file.*

**12. `Library::activeMedia()` is redundant (Phase 2.3)**
`SoftDeletes` adds a global scope that automatically excludes soft-deleted rows. The `activeMedia()` method in the plan's Phase 2.3 adds a redundant `->whereNull('deleted_at')` on a relationship that already excludes them. Remove the method entirely, or rename it to `withTrashedMedia()` using `->withTrashed()` if explicitly needed.
*Verified 2026-05-02: `activeMedia()` does not exist in the current `Library.php` — bug is in the plan's proposed code. Fix before writing the file.*

**13. `HasMediaItems::sort_order` increment is not atomic (Phase 3.1)**
`(int) ($this->media()->max('sort_order') ?? 0) + 1` is a read-then-increment that races under concurrent uploads and will produce duplicate sort orders. Wrap in a DB transaction or use `SELECT FOR UPDATE`.
*Verified 2026-05-02: `app/Traits/HasMediaItems.php` does not exist yet — bug is in the plan's Phase 3.1 code. Fix before writing the file.*

**14. `mediaForField()` runs an unbatched DB query on every call (FileUpload section)**
`Field::where('handle', $field)->value('id')` fires a query on every `mediaForField()` call. In list views with many models this becomes N queries. Cache the field ID lookup (e.g., `once()`) or require callers to pass an `int` in batch contexts.
*Verified 2026-05-02: `app/Traits/HasMedia.php` does not exist yet — bug is in the plan's proposed code. Fix before writing the file.*

**15. `App\Traits\HasMedia` namespace collision risk during transition**
`Spatie\MediaLibrary\HasMedia` is an interface; the new `App\Traits\HasMedia` is a trait with the same short name. `app/Models/Media/Library.php` currently `implements HasMedia` pointing to Spatie's interface (line 13). When Phase 3 introduces the new trait, all models that previously did `implements HasMedia` or `use InteractsWithMedia` need explicit import cleanup before the new trait is applied, or PHP will resolve the wrong symbol.
*Verified 2026-05-02: `app/Traits/HasMedia.php` does not exist yet. `Library.php` still has Spatie's `implements HasMedia`.*

---

### 🔵 Decisions / Behavior Changes to Flag Explicitly

**16. `User::avatar()` changes from Gravatar to media-first with Laravolt base64 fallback (Phase 8.2)**
The current method returns a Gravatar URL keyed on `$this->email` via `app(Avatar::class)->create($this->email)->toGravatar()`. The plan replaces it with a media-first approach: check the `avatars` library first, then fall back to `\Laravolt\Avatar\Facade::create($this->name)->toBase64()` — a base64 inline image keyed on **name** (not email), with no external request. This changes both the `src` type and the fallback image key. Flag for frontend and API consumers before merging.
*Verified 2026-05-02: `User::avatar()` still returns Gravatar.*

---

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

---

---

# File Upload Field Type — Implementation Plan

## Overview

`FileUpload` is a first-class field type in the Field layer, equal in standing to `Text`,
`Relationship`, and every other type. It can be added to any FieldGroup, placed in any
FieldLayout tab, and attached to any model that uses the `Fieldable` trait — Entries,
Categories, Users, and Media items themselves.

When a FileUpload field value is saved, the selected media IDs are stored in `value_json`
on the `field_values` row (the normal scalar path). A `FieldValueObserver` then keeps the
`mediables` pivot in sync so that `Media::usages()` always reflects every model that
references a given file, including which specific field made the reference.

---

## How It Fits the Existing Pipeline

```
Field (field_type_id → FileUpload Type)
  └── FieldValue (value_json = [3, 7, 12])   ← normal scalar storage
        └── resolvedValue()
              └── FileUpload::value($ids)
                    └── returns Collection<Media>   ← what callers receive

FieldValueObserver (on saved / deleted)
  └── syncs mediables pivot
        └── (media_id, mediable_type, mediable_id, field_id, sort_order)
```

The `Relationship` field type diverges from the scalar path entirely (`isRelational = true`,
data in `entry_relationships`). FileUpload does **not** do this — it stays on the normal
`value_json` path. The `mediables` pivot is a derived index maintained by the observer,
not the primary store.

---

## Schema Changes Required by This Feature

### Update `mediables` — add `field_id`

The `mediables` pivot already tracks which model instances reference which media. But a
single model could have both a `hero_image` field and a `gallery` field — two different
FileUpload fields pointing to different sets of media. Without `field_id` on the pivot,
syncing one field's selection would destroy the other's.

```php
Schema::table('mediables', function (Blueprint $table) {
    $table->foreignId('field_id')
        ->nullable()
        ->after('mediable_id')
        ->constrained('fields')
        ->nullOnDelete();
});
```

Updated unique constraint:

```php
// Drop old unique, add new one that includes field_id
$table->dropUnique('mediables_unique');
$table->unique(
    ['media_id', 'mediable_type', 'mediable_id', 'field_id'],
    'mediables_unique'
);
```

**Semantics:**
- `field_id = null` → media attached directly to a model (avatar, library browser, etc.)
- `field_id = X` → media attached via a specific FileUpload field on that model

This distinction lets `HasMedia::media()` and `HasMedia::mediaForField()` both work without
ambiguity.

---

## The Field Type Class

### `App\Field\Types\FileUpload`

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
     * Scalar storage — selected media IDs are kept in value_json.
     * The mediables pivot is a derived index; it is NOT the primary store.
     */
    public function storageColumn(): string
    {
        return 'value_json';
    }

    /**
     * Not relational in the Entry::field() sense — does not use entry_relationships.
     * Stays on the normal fieldValues path so it works identically on Entry,
     * Category, User, and Media without any model-specific special-casing.
     */
    public function isRelational(): bool
    {
        return false;
    }

    // ── Validation ─────────────────────────────────────────────────────────

    public function validate(mixed $value): bool|string
    {
        $ids = $this->normaliseIds($value);
        $min = (int) $this->getSetting('min', 0);
        $max = $this->getSetting('max');          // null = unlimited

        if ($min > 0 && count($ids) < $min) {
            $noun = $min === 1 ? 'file' : 'files';
            return "At least {$min} {$noun} must be selected.";
        }

        if ($max !== null && count($ids) > (int) $max) {
            $noun = (int) $max === 1 ? 'file' : 'files';
            return "No more than {$max} {$noun} may be selected.";
        }

        return true;
    }

    // ── Type contract ───────────────────────────────────────────────────────

    /**
     * Cast raw stored value to a plain array of integer IDs.
     * Called internally; callers receive resolved Media models via value().
     */
    public function cast(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        return [];
    }

    /**
     * Resolve stored IDs to Media models, preserving saved sort order.
     * This is what fieldValues->resolvedValue() ultimately returns to callers.
     *
     * Eager-load note: this produces one query per FileUpload field resolved.
     * In list contexts, pre-load via MediaRepository::forFieldValues() to batch.
     */
    public function value(mixed $raw): Collection
    {
        $ids = $this->cast($raw);

        if (empty($ids)) {
            return collect();
        }

        return Media::whereIn('id', $ids)
            ->get()
            ->sortBy(fn($m) => array_search($m->id, $ids))
            ->values();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Normalise any incoming value format to a flat array of integer IDs.
     * Used by the observer and validator to avoid duplicating cast logic.
     */
    public function normaliseIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->pluck('id')->map('intval')->all();
        }

        return $this->cast($value);
    }
}
```

**Settings stored in the `fields.settings` JSON column:**

| Key | Type | Default | Purpose |
|---|---|---|---|
| `library_id` | int\|null | null | Restrict picker to a specific library by ID |
| `library_handle` | string\|null | null | Alternative to `library_id`; portable across environments |
| `min` | int | 0 | Minimum number of files required |
| `max` | int\|null | null | Maximum files (null = unlimited; 1 = single-file mode) |
| `allowed_types` | array\|null | null | Override library MIME restrictions at field level |
| `show_preview` | bool | true | Whether the UI renders an inline preview |

---

## FieldValue Observer

The observer is the single point responsible for keeping the `mediables` pivot truthful.
Nothing else should write to that pivot for FileUpload field references.

### `App\Observers\FieldValueObserver`

```php
namespace App\Observers;

use App\Field\Types\FileUpload;
use App\Models\FieldValue;
use App\Models\Field\Type as FieldType;
use Illuminate\Support\Facades\DB;

class FieldValueObserver
{
    /**
     * After a FieldValue is created or updated, sync mediables if FileUpload.
     */
    public function saved(FieldValue $fieldValue): void
    {
        if (!$this->isFileUpload($fieldValue)) {
            return;
        }

        $this->syncMediables($fieldValue);
    }

    /**
     * After a FieldValue is deleted, remove its mediables rows.
     */
    public function deleted(FieldValue $fieldValue): void
    {
        if (!$this->isFileUpload($fieldValue)) {
            return;
        }

        DB::table('mediables')
            ->where('mediable_type', $fieldValue->fieldable_type)
            ->where('mediable_id',   $fieldValue->fieldable_id)
            ->where('field_id',      $fieldValue->field_id)
            ->delete();
    }

    // ── Internals ──────────────────────────────────────────────────────────

    private function isFileUpload(FieldValue $fieldValue): bool
    {
        // field and fieldType are always eager-loaded (see Field model $with)
        return $fieldValue->field?->fieldType?->object === FileUpload::class;
    }

    private function syncMediables(FieldValue $fieldValue): void
    {
        $type     = $fieldValue->fieldable_type;
        $id       = $fieldValue->fieldable_id;
        $fieldId  = $fieldValue->field_id;

        // Resolve the new set of media IDs from stored value_json
        $instance = $fieldValue->field->fieldType->instance();
        $newIds   = $instance->cast($fieldValue->value_json);

        // Remove pivot rows for this model+field that are no longer selected
        DB::table('mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id',   $id)
            ->where('field_id',      $fieldId)
            ->whereNotIn('media_id', $newIds)
            ->delete();

        // Upsert rows for newly selected media (preserves sort order)
        foreach ($newIds as $sortOrder => $mediaId) {
            DB::table('mediables')->upsert(
                [
                    'media_id'      => $mediaId,
                    'mediable_type' => $type,
                    'mediable_id'   => $id,
                    'field_id'      => $fieldId,
                    'sort_order'    => $sortOrder,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                ['media_id', 'mediable_type', 'mediable_id', 'field_id'],
                ['sort_order', 'updated_at']
            );
        }
    }
}
```

Register in `AppServiceProvider::boot()`:

```php
\App\Models\FieldValue::observe(\App\Observers\FieldValueObserver::class);
```

---

## `HasMedia` Trait — Enriched for Field-Scoped Queries

Add `mediaForField()` to the existing `HasMedia` trait so callers can retrieve media
attached specifically by a named field, separately from directly attached media (avatars,
library browser selections, etc.).

```php
// Add to App\Traits\HasMedia

use App\Models\Field;

/**
 * Return media attached to this model via a specific FileUpload field.
 *
 * @param  string|int  $field  Field handle (string) or field ID (int)
 */
public function mediaForField(string|int $field): MorphToMany
{
    $fieldId = is_int($field)
        ? $field
        : Field::where('handle', $field)->value('id');

    return $this->morphToMany(Media::class, 'mediable', 'mediables')
                ->wherePivot('field_id', $fieldId)
                ->withTimestamps()
                ->withPivot('sort_order', 'field_id')
                ->orderByPivot('sort_order');
}

/**
 * Return media attached directly (field_id IS NULL) — avatars, browser picks, etc.
 */
public function directMedia(): MorphToMany
{
    return $this->morphToMany(Media::class, 'mediable', 'mediables')
                ->wherePivotNull('field_id')
                ->withTimestamps()
                ->withPivot('sort_order')
                ->orderByPivot('sort_order');
}
```

The existing `media()` relationship (no filter on `field_id`) continues to return everything
— useful for usage counts and bulk operations.

---

## `Media` Model — Inverse Usage Relationship

The `usages()` method already planned in the main refactor returns all `mediables` rows.
Add a `fieldUsages()` scope for querying specifically which field-driven references exist:

```php
// Add to App\Models\Media

use App\Models\Field;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mediables rows that came from a FileUpload field (field_id IS NOT NULL).
 */
public function fieldUsages(): \Illuminate\Database\Eloquent\Builder
{
    return \DB::table('mediables')
        ->where('media_id', $this->id)
        ->whereNotNull('field_id')
        ->join('fields', 'fields.id', '=', 'mediables.field_id')
        ->select('mediables.*', 'fields.name as field_name', 'fields.handle as field_handle');
}

/**
 * Convenience: is this media used by any field on any model?
 */
public function isReferencedByField(): bool
{
    return \DB::table('mediables')
        ->where('media_id', $this->id)
        ->whereNotNull('field_id')
        ->exists();
}
```

---

## FieldTypeSeeder Update

```php
// In database/seeders/FieldTypeSeeder.php — add to the $types array:

['name' => 'File Upload', 'object' => \App\Field\Types\FileUpload::class],
```

---

## Example Field Seeds

These demonstrate FileUpload fields across all four model contexts. Add a
`MediaFieldGroupSeeder` that runs after `FieldTypeSeeder`:

```php
namespace Database\Seeders;

use App\Field\Types\FileUpload;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MediaFieldGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    private FieldType $fileUpload;

    public function run(): void
    {
        $this->fileUpload = FieldType::where('object', FileUpload::class)->firstOrFail();

        $this->seedUserMediaFields();
        $this->seedEntryMediaFields();
        $this->seedCategoryMediaFields();
        $this->seedMediaMetaFields();
    }

    // ── User ───────────────────────────────────────────────────────────────

    private function seedUserMediaFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'user-media-fields'],
            ['name' => 'User Media', 'description' => 'File fields for user profiles.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Profile Photo',
                'handle'        => 'profile_photo',
                'label'         => 'Profile Photo',
                'instructions'  => 'Upload a profile photo. Used in place of Gravatar when set.',
                'settings'      => [
                    'library_handle' => 'avatars',
                    'max'            => 1,
                    'allowed_types'  => ['image/jpeg', 'image/png', 'image/webp'],
                    'show_preview'   => true,
                ],
            ],
        ]);
    }

    // ── Entry ──────────────────────────────────────────────────────────────

    private function seedEntryMediaFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'entry-media-fields'],
            ['name' => 'Entry Media', 'description' => 'Common media fields for entries.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Hero Image',
                'handle'        => 'hero_image',
                'label'         => 'Hero Image',
                'instructions'  => 'Primary image displayed at the top of the entry.',
                'settings'      => [
                    'max'           => 1,
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
                    'show_preview'  => true,
                ],
            ],
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Gallery',
                'handle'        => 'gallery',
                'label'         => 'Gallery',
                'instructions'  => 'Additional images displayed in a gallery.',
                'settings'      => [
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    'show_preview'  => true,
                ],
            ],
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Attachments',
                'handle'        => 'attachments',
                'label'         => 'Attachments',
                'instructions'  => 'Downloadable files attached to this entry.',
                'settings'      => [],   // no type restriction — library controls it
            ],
        ]);
    }

    // ── Category ───────────────────────────────────────────────────────────

    private function seedCategoryMediaFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'category-media-fields'],
            ['name' => 'Category Media', 'description' => 'Image fields for categories.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Category Image',
                'handle'        => 'category_image',
                'label'         => 'Category Image',
                'instructions'  => 'Displayed when this category is shown in lists or headers.',
                'settings'      => [
                    'max'           => 1,
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
                    'show_preview'  => true,
                ],
            ],
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Category Icon',
                'handle'        => 'category_icon',
                'label'         => 'Icon',
                'instructions'  => 'Small icon used in navigation or tag lists.',
                'settings'      => [
                    'max'           => 1,
                    'allowed_types' => ['image/svg+xml', 'image/png', 'image/webp'],
                    'show_preview'  => true,
                ],
            ],
        ]);
    }

    // ── Media (fields on Media items themselves) ───────────────────────────

    private function seedMediaMetaFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'media-meta-fields'],
            ['name' => 'Media Meta', 'description' => 'Custom fields for media items — related files, variant packs, etc.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Related Files',
                'handle'        => 'related_files',
                'label'         => 'Related Files',
                'instructions'  => 'Other media items associated with this file (e.g. print-ready version, source file).',
                'settings'      => [],
            ],
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function attachFields(FieldGroup $group, array $fields): void
    {
        foreach ($fields as $data) {
            $field = Field::firstOrCreate(['handle' => $data['handle']], $data);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }
    }
}
```

---

## Wiring FileUpload Fields into FieldLayouts

Use the existing `BuildsLayouts` concern (already in `database/seeders/Concerns/`)
unchanged. FileUpload field handles slot into `createLayout()` and `addTabIfMissing()`
exactly like any other field handle:

```php
// In EntryGroupSeeder or ExtendedEntryGroupSeeder:
$this->createLayout('Blog Post', [
    'Content' => ['body', 'excerpt'],
    'Media'   => ['hero_image', 'gallery'],     // FileUpload fields drop in here
    'SEO'     => ['meta_title', 'meta_description'],
]);

// In UserSchemaSeeder (or equivalent):
$this->addTabIfMissing($layout->id, 'Profile', ['profile_photo'], sortOrder: 1);
```

No changes to `BuildsLayouts`, `FieldLayout`, `Tab`, or `TabElement` are required.

---

## How Callers Use It

### Entry (via `Fieldable` trait + `Entry::field()` override)

```php
// Returns Collection<Media> — resolved by FileUpload::value()
$heroImages = $entry->field('hero_image');
$gallery    = $entry->field('gallery');

// Direct pivot query (via HasMedia::mediaForField)
$heroImages = $entry->mediaForField('hero_image')->get();

// All media on this entry regardless of field
$allMedia   = $entry->media()->get();
```

### User

```php
// Via the Fieldable trait — same as Entry
$photo = $user->field('profile_photo')?->first();

// Or via the dedicated avatar() method which wraps this
$avatarUrl = $user->avatar();
```

### Category

```php
$image = $category->field('category_image')?->first();
$icon  = $category->field('category_icon')?->first();
```

### Media (field on a Media item itself)

```php
// A media item's own related-files field
$related = $mediaItem->field('related_files');
```

### Media usage index (inverse)

```php
// "Which fields on which models use this file?"
$usageRows = $mediaItem->fieldUsages()->get();
// Returns: mediable_type, mediable_id, field_id, field_name, field_handle, sort_order

// "Is this file referenced anywhere via a field?"
$isInUse = $mediaItem->isReferencedByField();
```

---

## Eager-Loading to Avoid N+1

`FileUpload::value()` runs one `Media::whereIn()` query per resolved field. In list
views with many items this becomes expensive. Batch it:

```php
// In a repository or service layer — batch-load media for all entries at once

$entryIds = $entries->pluck('id');

// 1. Load all field values for all entries in one query
$fieldValues = FieldValue::whereIn('fieldable_id', $entryIds)
    ->where('fieldable_type', 'entry')
    ->with('field.fieldType')
    ->get()
    ->groupBy('fieldable_id');

// 2. Collect all media IDs across all FileUpload values
$allMediaIds = $fieldValues->flatten()
    ->filter(fn($fv) => $fv->field?->fieldType?->object === FileUpload::class)
    ->flatMap(fn($fv) => json_decode($fv->value_json ?? '[]', true))
    ->unique();

// 3. Load all media in one query, key by ID
$mediaById = Media::whereIn('id', $allMediaIds)->get()->keyBy('id');

// 4. Attach to entries — no further queries
foreach ($entries as $entry) {
    $entry->setRelation('preloadedMedia', $mediaById);
}
```

For smaller contexts (single entry edit, user profile) the per-field query is fine.

---

## Testing Approach

```php
// Feature test outline

it('stores media IDs in value_json and resolves to Media models', function () {
    $library = MediaLibrary::factory()->create(['adapter' => 'local']);
    $media   = Media::factory()->for($library)->createMany(3);
    $field   = Field::factory()->fileUpload()->create();
    $entry   = Entry::factory()->create();

    // Save field value
    FieldValue::create([
        'field_id'      => $field->id,
        'fieldable_id'  => $entry->id,
        'fieldable_type'=> 'entry',
        'value_json'    => $media->pluck('id')->toJson(),
    ]);

    // Resolved value returns Collection<Media>
    $resolved = $entry->fresh(['fieldValues.field.fieldType'])->field($field->handle);
    expect($resolved)->toHaveCount(3);
    expect($resolved->first())->toBeInstanceOf(Media::class);
});

it('syncs mediables pivot on save', function () {
    // … create FieldValue with media IDs, assert mediables rows exist …
    // … update with fewer IDs, assert removed rows are gone …
});

it('removes mediables rows on field value delete', function () {
    // … create, then delete FieldValue, assert pivot rows gone …
});

it('mediaForField scopes correctly with multiple FileUpload fields', function () {
    // … entry with hero_image (1 file) and gallery (3 files) …
    // … assert mediaForField('hero_image') returns 1, gallery returns 3 …
    // … assert media() returns all 4 …
});

it('works identically on Category, User, and Media models', function () {
    // Same assertions repeated for each model type
});
```

---

## Implementation Order for This Feature

These steps run after the core media refactor (Phases 1–10) is complete.

1. **`mediables` migration update** — add nullable `field_id`, update unique constraint
2. **`FileUpload` class** — `App\Field\Types\FileUpload`
3. **`FieldValueObserver`** — register in `AppServiceProvider::boot()`
4. **`HasMedia` trait update** — `mediaForField()`, `directMedia()`
5. **`Media` model update** — `fieldUsages()`, `isReferencedByField()`
6. **`FieldTypeSeeder`** — add FileUpload row
7. **`MediaFieldGroupSeeder`** — add to `DatabaseSeeder` after `FieldTypeSeeder`
8. **`BuildsLayouts` usage** — add `hero_image`, `gallery`, `profile_photo` to relevant existing layout seeders
9. **Tests**

---

---

# File Upload Field Type — Implementation Plan

## Overview

`FileUpload` is a first-class field type in the Field layer, equal in standing to `Text`,
`Relationship`, and every other type. It can be added to any FieldGroup, placed in any
FieldLayout tab, and attached to any model that uses the `Fieldable` trait — Entries,
Categories, Users, and Media items themselves.

When a FileUpload field value is saved, the selected media IDs are stored in `value_json`
on the `field_values` row (the normal scalar path). A `FieldValueObserver` then keeps the
`mediables` pivot in sync so that `Media::usages()` always reflects every model that
references a given file, including which specific field made the reference.

---

## How It Fits the Existing Pipeline

```
Field (field_type_id → FileUpload Type)
  └── FieldValue (value_json = [3, 7, 12])   ← normal scalar storage
        └── resolvedValue()
              └── FileUpload::value($ids)
                    └── returns Collection<Media>   ← what callers receive

FieldValueObserver (on saved / deleted)
  └── syncs mediables pivot
        └── (media_id, mediable_type, mediable_id, field_id, sort_order)
```

The `Relationship` field type diverges from the scalar path entirely (`isRelational = true`,
data in `entry_relationships`). FileUpload does **not** do this — it stays on the normal
`value_json` path. The `mediables` pivot is a derived index maintained by the observer,
not the primary store.

---

## Schema Changes Required by This Feature

### Update `mediables` — add `field_id`

The `mediables` pivot already tracks which model instances reference which media. But a
single model could have both a `hero_image` field and a `gallery` field — two different
FileUpload fields pointing to different sets of media. Without `field_id` on the pivot,
syncing one field's selection would destroy the other's.

```php
Schema::table('mediables', function (Blueprint $table) {
    $table->foreignId('field_id')
        ->nullable()
        ->after('mediable_id')
        ->constrained('fields')
        ->nullOnDelete();

    // Drop old unique, replace with one that includes field_id
    $table->dropUnique('mediables_unique');
    $table->unique(
        ['media_id', 'mediable_type', 'mediable_id', 'field_id'],
        'mediables_unique'
    );
});
```

**Semantics of `field_id`:**
- `null` → media attached directly to a model (avatar upload, library browser selection, etc.)
- `N` → media attached via a specific FileUpload field on that model

This lets `HasMedia::media()`, `HasMedia::directMedia()`, and `HasMedia::mediaForField()`
all coexist without collision.

---

## The Field Type Class

### `App\Field\Types\FileUpload`

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
     * Scalar storage — selected media IDs live in value_json.
     * The mediables pivot is a derived index; it is NOT the primary store.
     */
    public function storageColumn(): string
    {
        return 'value_json';
    }

    /**
     * Not relational in the Entry sense — does not use entry_relationships.
     * Stays on the normal fieldValues path so it works identically on Entry,
     * Category, User, and Media without any model-specific special-casing.
     */
    public function isRelational(): bool
    {
        return false;
    }

    // ── Validation ─────────────────────────────────────────────────────

    public function validate(mixed $value): bool|string
    {
        $ids = $this->normaliseIds($value);
        $min = (int) $this->getSetting('min', 0);
        $max = $this->getSetting('max');          // null = unlimited

        if ($min > 0 && count($ids) < $min) {
            $noun = $min === 1 ? 'file' : 'files';
            return "At least {$min} {$noun} must be selected.";
        }

        if ($max !== null && count($ids) > (int) $max) {
            $noun = (int) $max === 1 ? 'file' : 'files';
            return "No more than {$max} {$noun} may be selected.";
        }

        return true;
    }

    // ── Type contract ───────────────────────────────────────────────────

    /**
     * Cast raw stored JSON to a plain array of integer IDs.
     */
    public function cast(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        return [];
    }

    /**
     * Resolve stored IDs to Media models, preserving saved sort order.
     * This is what FieldValue::resolvedValue() ultimately returns to callers —
     * so $entry->field('gallery') returns Collection<Media>, not raw IDs.
     *
     * Eager-load note: produces one Media query per FileUpload field resolved.
     * Batch via MediaRepository::forFieldValues() in list contexts.
     */
    public function value(mixed $raw): Collection
    {
        $ids = $this->cast($raw);

        if (empty($ids)) {
            return collect();
        }

        return Media::whereIn('id', $ids)
            ->get()
            ->sortBy(fn($m) => array_search($m->id, $ids))
            ->values();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Normalise any incoming value format to a flat array of integer IDs.
     * Shared by the observer and validator to avoid duplicating cast logic.
     */
    public function normaliseIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->pluck('id')->map('intval')->all();
        }
        return $this->cast($value);
    }
}
```

**Settings stored in `fields.settings` JSON:**

| Key | Type | Default | Purpose |
|---|---|---|---|
| `library_id` | int\|null | null | Restrict picker to a specific library by ID |
| `library_handle` | string\|null | null | Alternative to `library_id`; portable across environments |
| `min` | int | 0 | Minimum files required (0 = optional) |
| `max` | int\|null | null | Maximum files (null = unlimited; 1 = single-file mode) |
| `allowed_types` | array\|null | null | Override library MIME types at field level |
| `show_preview` | bool | true | Whether the UI renders an inline file preview |

---

## FieldValue Observer

The observer is the single point responsible for keeping the `mediables` pivot truthful.
Nothing else should write field-scoped rows to that pivot.

### `App\Observers\FieldValueObserver`

```php
namespace App\Observers;

use App\Field\Types\FileUpload;
use App\Models\FieldValue;
use Illuminate\Support\Facades\DB;

class FieldValueObserver
{
    /**
     * After a FieldValue is created or updated, sync mediables if FileUpload.
     */
    public function saved(FieldValue $fieldValue): void
    {
        if (!$this->isFileUpload($fieldValue)) {
            return;
        }

        $this->syncMediables($fieldValue);
    }

    /**
     * After a FieldValue is deleted, remove its mediables rows.
     */
    public function deleted(FieldValue $fieldValue): void
    {
        if (!$this->isFileUpload($fieldValue)) {
            return;
        }

        DB::table('mediables')
            ->where('mediable_type', $fieldValue->fieldable_type)
            ->where('mediable_id',   $fieldValue->fieldable_id)
            ->where('field_id',      $fieldValue->field_id)
            ->delete();
    }

    // ── Internals ──────────────────────────────────────────────────────

    private function isFileUpload(FieldValue $fieldValue): bool
    {
        // field and fieldType are always eager-loaded via Field model's $with
        return $fieldValue->field?->fieldType?->object === FileUpload::class;
    }

    private function syncMediables(FieldValue $fieldValue): void
    {
        $type    = $fieldValue->fieldable_type;
        $id      = $fieldValue->fieldable_id;
        $fieldId = $fieldValue->field_id;

        $instance = $fieldValue->field->fieldType->instance();
        $newIds   = $instance->cast($fieldValue->value_json);

        // Remove pivot rows for this model+field that are no longer selected
        DB::table('mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id',   $id)
            ->where('field_id',      $fieldId)
            ->whereNotIn('media_id', $newIds)
            ->delete();

        // Upsert rows for newly selected media (preserves sort order position)
        foreach ($newIds as $sortOrder => $mediaId) {
            DB::table('mediables')->upsert(
                [
                    'media_id'      => $mediaId,
                    'mediable_type' => $type,
                    'mediable_id'   => $id,
                    'field_id'      => $fieldId,
                    'sort_order'    => $sortOrder,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                ['media_id', 'mediable_type', 'mediable_id', 'field_id'],
                ['sort_order', 'updated_at']
            );
        }
    }
}
```

Register in `AppServiceProvider::boot()`:

```php
\App\Models\FieldValue::observe(\App\Observers\FieldValueObserver::class);
```

---

## `HasMedia` Trait — Field-Scoped Additions

Add to `App\Traits\HasMedia` alongside the existing `media()` and `attachMedia()` methods:

```php
use App\Models\Field;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Media attached via a specific FileUpload field on this model.
 *
 * @param  string|int  $field  Field handle or ID
 */
public function mediaForField(string|int $field): MorphToMany
{
    $fieldId = is_int($field)
        ? $field
        : Field::where('handle', $field)->value('id');

    return $this->morphToMany(Media::class, 'mediable', 'mediables')
                ->wherePivot('field_id', $fieldId)
                ->withTimestamps()
                ->withPivot('sort_order', 'field_id')
                ->orderByPivot('sort_order');
}

/**
 * Media attached directly to this model — not via any field.
 * Covers avatars, browser selections, and any direct attachMedia() call.
 */
public function directMedia(): MorphToMany
{
    return $this->morphToMany(Media::class, 'mediable', 'mediables')
                ->wherePivotNull('field_id')
                ->withTimestamps()
                ->withPivot('sort_order')
                ->orderByPivot('sort_order');
}
```

The existing `media()` (no filter) continues to return everything — useful for usage
totals and bulk operations.

---

## `Media` Model — Inverse Field-Usage Queries

Add to `App\Models\Media`:

```php
/**
 * Raw query of mediables rows that came from a FileUpload field.
 * Returns: media_id, mediable_type, mediable_id, field_id, field_name, field_handle
 */
public function fieldUsages(): \Illuminate\Support\Collection
{
    return \DB::table('mediables')
        ->where('media_id', $this->id)
        ->whereNotNull('field_id')
        ->join('fields', 'fields.id', '=', 'mediables.field_id')
        ->select(
            'mediables.mediable_type',
            'mediables.mediable_id',
            'mediables.field_id',
            'mediables.sort_order',
            'fields.name as field_name',
            'fields.handle as field_handle',
        )
        ->get();
}

/**
 * Quick check: is this file referenced by any FileUpload field on any model?
 * Useful before soft-deleting to warn the user of active references.
 */
public function isReferencedByField(): bool
{
    return \DB::table('mediables')
        ->where('media_id', $this->id)
        ->whereNotNull('field_id')
        ->exists();
}
```

---

## FieldTypeSeeder Update

```php
// database/seeders/FieldTypeSeeder.php — add to $types array:

['name' => 'File Upload', 'object' => \App\Field\Types\FileUpload::class],
```

---

## Example Field Groups Seeder

Add `database/seeders/MediaFieldGroupSeeder.php`:

```php
namespace Database\Seeders;

use App\Field\Types\FileUpload;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MediaFieldGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    private FieldType $fileUpload;

    public function run(): void
    {
        $this->fileUpload = FieldType::where('object', FileUpload::class)->firstOrFail();

        $this->seedUserMediaFields();
        $this->seedEntryMediaFields();
        $this->seedCategoryMediaFields();
        $this->seedMediaItemFields();
    }

    private function seedUserMediaFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'user-media-fields'],
            ['name' => 'User Media', 'description' => 'File fields for user profiles.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Profile Photo',
                'handle'        => 'profile_photo',
                'label'         => 'Profile Photo',
                'instructions'  => 'Shown in place of Gravatar when set.',
                'settings'      => [
                    'library_handle' => 'avatars',
                    'max'            => 1,
                    'allowed_types'  => ['image/jpeg', 'image/png', 'image/webp'],
                    'show_preview'   => true,
                ],
            ],
        ]);
    }

    private function seedEntryMediaFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'entry-media-fields'],
            ['name' => 'Entry Media', 'description' => 'Common media fields for entries.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Hero Image',
                'handle'        => 'hero_image',
                'label'         => 'Hero Image',
                'instructions'  => 'Primary image displayed at the top of the entry.',
                'settings'      => [
                    'max'           => 1,
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
                    'show_preview'  => true,
                ],
            ],
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Gallery',
                'handle'        => 'gallery',
                'label'         => 'Gallery',
                'instructions'  => 'Additional images displayed in an inline gallery.',
                'settings'      => [
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    'show_preview'  => true,
                ],
            ],
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Attachments',
                'handle'        => 'attachments',
                'label'         => 'Attachments',
                'instructions'  => 'Downloadable files attached to this entry.',
                'settings'      => [],  // no MIME restriction — inherits from library
            ],
        ]);
    }

    private function seedCategoryMediaFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'category-media-fields'],
            ['name' => 'Category Media', 'description' => 'Image fields for categories.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Category Image',
                'handle'        => 'category_image',
                'label'         => 'Category Image',
                'instructions'  => 'Displayed when this category appears in lists or headers.',
                'settings'      => [
                    'max'           => 1,
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
                    'show_preview'  => true,
                ],
            ],
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Category Icon',
                'handle'        => 'category_icon',
                'label'         => 'Icon',
                'instructions'  => 'Small icon for navigation or tag lists.',
                'settings'      => [
                    'max'           => 1,
                    'allowed_types' => ['image/svg+xml', 'image/png', 'image/webp'],
                    'show_preview'  => true,
                ],
            ],
        ]);
    }

    private function seedMediaItemFields(): void
    {
        // FileUpload fields on Media items themselves — for associating related files
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'media-item-fields'],
            ['name' => 'Media Item Fields', 'description' => 'Custom fields on individual media items.']
        );

        $this->attachFields($group, [
            [
                'field_type_id' => $this->fileUpload->id,
                'name'          => 'Related Files',
                'handle'        => 'related_files',
                'label'         => 'Related Files',
                'instructions'  => 'Other files associated with this item (e.g. print-ready version, source file).',
                'settings'      => [],
            ],
        ]);
    }

    private function attachFields(FieldGroup $group, array $fields): void
    {
        foreach ($fields as $data) {
            $field = Field::firstOrCreate(['handle' => $data['handle']], $data);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }
    }
}
```

Add to `DatabaseSeeder` after `FieldTypeSeeder`:

```php
$this->call([
    FieldTypeSeeder::class,
    MediaFieldGroupSeeder::class,  // add here
    FieldGroupSeeder::class,
    // ...
]);
```

---

## Wiring into FieldLayouts

`BuildsLayouts` requires no changes. FileUpload field handles slot in exactly like
any other handle:

```php
// EntryGroupSeeder / ExtendedEntryGroupSeeder
$this->createLayout('Blog Post', [
    'Content' => ['body', 'excerpt'],
    'Media'   => ['hero_image', 'gallery', 'attachments'],
    'SEO'     => ['meta_title', 'meta_description'],
]);

// UserSchemaSeeder (or wherever user layout is built)
$this->addTabIfMissing($layout->id, 'Profile', ['profile_photo'], sortOrder: 1);

// CategoryGroupSeeder
$this->createLayout('Blog Category', [
    'Details' => ['category_image', 'category_icon'],
]);

// MediaLibrary layout seeder (new — controls what meta fields appear on media items)
$this->createLayout('Media Library Fields', [
    'Meta' => ['related_files'],
]);
```

---

## How Callers Use It

```php
// Entry — via Fieldable::field() → FieldValue::resolvedValue() → FileUpload::value()
$hero    = $entry->field('hero_image')?->first();     // ?Media
$gallery = $entry->field('gallery');                   // Collection<Media>

// Scoped via HasMedia::mediaForField() — direct pivot query, no field_values involved
$gallery = $entry->mediaForField('gallery')->get();

// All media on this entry regardless of how it got there
$all = $entry->media()->get();

// Only directly-attached media (not via fields)
$direct = $entry->directMedia()->get();

// User
$photo     = $user->field('profile_photo')?->first();
$avatarUrl = $user->avatar();   // uses firstMedia('avatars') fallback logic

// Category
$image = $category->field('category_image')?->first();
$icon  = $category->field('category_icon')?->first();

// Media item — a file's own related-files field
$related = $mediaItem->field('related_files');

// Inverse — "who is using this file via a field?"
$usages  = $mediaItem->fieldUsages();
$inUse   = $mediaItem->isReferencedByField();
```

---

## Eager-Loading in List Contexts

`FileUpload::value()` issues one `Media::whereIn()` per field resolved. Batch it in
services or repositories that render lists:

```php
// Collect all media IDs across all FileUpload values for a set of entries
$allMediaIds = $entries
    ->flatMap->fieldValues
    ->filter(fn($fv) => $fv->field?->fieldType?->object === FileUpload::class)
    ->flatMap(fn($fv) => json_decode($fv->value_json ?? '[]', true))
    ->unique()
    ->values();

$mediaById = Media::whereIn('id', $allMediaIds)->get()->keyBy('id');
// Distribute $mediaById to the view — no further queries needed per entry
```

For single-record views (entry edit, user profile) the per-field query is fine.

---

## Testing Approach

```php
it('stores media IDs in value_json and resolves to Media models', function () {
    $media = Media::factory()->createMany(3);
    $field = Field::factory()->fileUpload()->create();
    $entry = Entry::factory()->create();

    FieldValue::create([
        'field_id'       => $field->id,
        'fieldable_id'   => $entry->id,
        'fieldable_type' => 'entry',
        'value_json'     => $media->pluck('id')->toJson(),
    ]);

    $resolved = $entry->fresh(['fieldValues.field.fieldType'])->field($field->handle);
    expect($resolved)->toHaveCount(3)->each->toBeInstanceOf(Media::class);
});

it('syncs the mediables pivot when a FileUpload FieldValue is saved', function () {
    // Create FieldValue with 3 media IDs, assert 3 mediables rows exist
    // Update to 2 IDs, assert removed row is gone, 2 remain
});

it('clears mediables rows when a FileUpload FieldValue is deleted', function () {
    // Create FieldValue, assert pivot rows, delete FieldValue, assert rows gone
});

it('mediaForField scopes correctly when multiple FileUpload fields exist', function () {
    // Entry with hero_image (1 file) and gallery (3 files)
    // mediaForField('hero_image') returns 1
    // mediaForField('gallery') returns 3
    // media() returns all 4
    // directMedia() returns 0
});

it('works on Category', function () { /* same pattern */ });
it('works on User',     function () { /* same pattern */ });
it('works on Media',    function () { /* same pattern — field on a media item */ });

it('respects min/max validation settings', function () {
    $type = new FileUpload(['min' => 1, 'max' => 3], null);
    expect($type->validate([]))->toBeString();          // fails — below min
    expect($type->validate([1, 2, 3, 4]))->toBeString(); // fails — above max
    expect($type->validate([1, 2]))->toBeTrue();         // passes
});
```

---

## Implementation Order for This Feature

Run after the core media refactor (Phases 1–10) is complete:

1. **`mediables` alter migration** — add nullable `field_id`, rebuild unique constraint
2. **`App\Field\Types\FileUpload`** — the field type class
3. **`App\Observers\FieldValueObserver`** — register in `AppServiceProvider::boot()`
4. **`HasMedia` additions** — `mediaForField()`, `directMedia()`
5. **`Media` model additions** — `fieldUsages()`, `isReferencedByField()`
6. **`FieldTypeSeeder`** — add the FileUpload row
7. **`MediaFieldGroupSeeder`** — add, wire into `DatabaseSeeder`
8. **Layout seeders** — add `hero_image`, `gallery`, `profile_photo`, etc. to existing layouts
9. **Tests**
