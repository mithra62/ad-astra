# Media Layer — Implementation Plan

*Revised 2026-05-07. Written against the actual codebase state after Spatie
MediaLibrary and Spatie Tags have been removed from `composer.json`. All step
descriptions start from what literally exists in the files today.*

---

## Actual Starting State

### What is already in place

**Database tables (confirmed by reading migrations):**

`media` — current columns: `id`, `uuid` (nullable unique), `collection_name`,
`name`, `file_name`, `mime_type`, `disk`, `size`, `order_column`,
`created_at`, `updated_at`. No `library_id`, no `path`, no soft-delete column.

`media_libraries` — current columns: `id`, `name`, `handle`, `adapter`,
`adapter_settings`, `allowed_types`, `max_size`, `sort_order`, `created_at`,
`updated_at`. No `field_layout_id`.

No `mediables` table. No `media_transformations` table.

**Models:**

`app/Models/Media.php` — extends `Model`, but still carries a dead import:
`use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;`
(the package is gone; this will fatal if autoloaded without the vendor dir).
Has a `media_library()` BelongsTo (wrong name, broken FK assumption) and a
`categories()` MorphToMany. No traits, no storage helpers.

`app/Models/Media/Library.php` — clean, no Spatie. Has `$table =
'media_libraries'`. Has **inline** snake_case relations `category_groups()` and
`field_groups()` implemented directly via `morphToMany()`, plus a manual
`categories()` helper. Does **not** use `HasCategoryGroups`, `HasFieldGroups`,
or `HasFieldLayout` traits even though those traits exist in `app/Traits/`.

**Traits that already exist in `app/Traits/`:**
`Fieldable`, `HasCategoryGroups`, `HasFieldGroups`, `HasFieldLayout`,
`HasEntryTree`, `PersistsFieldValues`, `PasswordValidationRules`.

**Traits that do not exist yet:**
`HasTransformations`, `HasMediaItems`, `HasMedia`.

**Actions:**

`App\Actions\Media\Library\CreateNewMediaLibrary` — functional but uses
`$library->category_groups()->attach()` (snake_case, matches current inline
method on Library).

`App\Actions\Media\Library\EditMediaLibrary` — functional but uses
`$library->category_groups()->detach()/attach()` and
`$library->field_groups()->detach()/attach()` (same snake_case issue).

`App\Actions\Media\Library\DeleteMediaLibrary` — calls `$library->delete()`
only; has a `@todo` comment about queue dispatch. No job is dispatched.

`App\Actions\Media\Library\UploadMedia` — broken; calls
`$library->addMedia($path)->toMediaCollection($library->handle)` which is the
old Spatie API.

No `App\Actions\Media\DeleteMedia` action yet.

**Jobs:**

`App\Jobs\ProcessMediaLibraryRemoval` — exists with an empty `handle()`.

**Services / Interfaces:**
None in `app/Services/Media/` — directory does not exist.

**Observers:**
`app/Observers/EntryTreeObserver.php` and `StatusObserver.php` exist.
No `FieldValueObserver`.

**Controllers:**

`App\Http\Controllers\Admin\Media\Library` — full CRUD wired to the four
Library actions. Uses `LibraryModel::with('category_groups')` (snake_case)
in `create()` and `edit()`. All method bodies are functional.

`App\Http\Controllers\Admin\Media` — mostly empty stubs. `download()` exists
but just returns the model, not a file response.

**Form Requests:**

`StoreMediaLibraryFormRequest` — validates key `'storage'` instead of
`'adapter'`; conditional block also keys on `'storage'`. No Blade views for
media library forms exist yet, so only the PHP file needs updating.

**`FieldValue::resolvedValue()`** — the method body instantiates
`$fieldType->instance()` twice (once for `storageColumn()`, once for
`value()`). It always calls `value()` unconditionally, which works today
because the base `AbstractField::value()` is a pass-through. This is fragile
and wastes a second instantiation.

**`FieldTypeSeeder`** — has 10 entries; no `FileUpload` row.

**`AppServiceProvider`** — morph map already contains both `'media'` and
`'media_library'` keys. No media service, no transformation driver binding,
no `FieldValueObserver` registration.

**`User` model** — uses `Laravolt\Avatar\Facade` for a generated avatar
fallback. Does **not** use `HasTags` or `HasMedia`. `laravolt/avatar` is in
`composer.json`.

---

## Do not start two steps in parallel.

Several steps share migrations and models. Follow the numbered order.

---

## Step 1 — Rewrite the `media` Table

The current `media` table has two Spatie remnants (`collection_name`,
`order_column`) and is missing the native columns the rest of this plan
requires (`library_id`, `path`, `original_name`, `alt_text`, `title`,
`sort_order`, `deleted_at`).

**File:** `database/migrations/YYYY_MM_DD_000001_rewrite_media_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Remove the two Spatie remnant columns that actually exist.
            // Do NOT list any other columns — they were never added to this table.
            $table->dropColumn(['collection_name', 'order_column']);

            // Link to the library that owns this file.
            $table->foreignId('library_id')
                  ->nullable()
                  ->after('uuid')
                  ->constrained('media_libraries')
                  ->nullOnDelete();

            // Native columns
            $table->string('original_name')->after('file_name');
            $table->string('path')->after('disk');
            $table->string('alt_text')->nullable()->after('path');
            $table->string('title')->nullable()->after('alt_text');
            $table->unsignedInteger('sort_order')->default(0)->after('title');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['library_id']);
            $table->dropColumn([
                'library_id', 'original_name', 'path',
                'alt_text', 'title', 'sort_order', 'deleted_at',
            ]);
            $table->string('collection_name')->after('uuid');
            $table->unsignedInteger('order_column')->nullable()->index()->after('size');
        });
    }
};
```

> **Final `media` column set after this migration:**
> `id`, `uuid`, `library_id`, `name`, `file_name`, `original_name`,
> `mime_type`, `disk`, `path`, `size`, `alt_text`, `title`, `sort_order`,
> `created_at`, `updated_at`, `deleted_at`.

---

## Step 2 — Create the `mediables` Pivot Table

Many-to-many link between any model and the media it references.
`field_id = null` means a direct attachment (avatar, library browser pick).
`field_id = N` means it was attached through a specific FileUpload field,
which lets `mediaForField()` and `directMedia()` coexist cleanly.

**File:** `database/migrations/YYYY_MM_DD_000002_create_mediables_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('mediable');                 // mediable_type, mediable_id
            $table->foreignId('field_id')
                  ->nullable()
                  ->constrained('fields')
                  ->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['media_id', 'mediable_type', 'mediable_id', 'field_id'],
                'mediables_unique'
            );
            $table->index(['mediable_type', 'mediable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};
```

---

## Step 3 — Create the `media_transformations` Table

Stores derived image variants. The transformation library is not chosen yet;
this schema is library-agnostic and the driver is swapped in Step 11.

**File:** `database/migrations/YYYY_MM_DD_000003_create_media_transformations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_transformations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();

            $table->string('key');                       // e.g. 'thumbnail', 'hero_2x'
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('params')->nullable();          // driver-agnostic params
            $table->string('driver')->nullable();        // set once library is chosen

            $table->string('status')->default('pending'); // pending | complete | failed
            $table->text('error')->nullable();

            $table->timestamps();
            $table->unique(['media_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_transformations');
    }
};
```

---

## Step 4 — Add `field_layout_id` to `media_libraries`

Follows the same pattern as `EntryGroup`. The column does not exist yet.

**File:** `database/migrations/YYYY_MM_DD_000004_add_field_layout_id_to_media_libraries.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->foreignId('field_layout_id')
                  ->nullable()
                  ->after('handle')
                  ->constrained('field_layouts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->dropForeign(['field_layout_id']);
            $table->dropColumn('field_layout_id');
        });
    }
};
```

**Run all four migrations:**

```bash
php artisan migrate
```

---

## Step 5 — Rewrite `App\Models\Media`

Replace the current file in full. Remove the dead Spatie import and the
mis-named `media_library()` relation. Add traits and storage helpers.

**File:** `app/Models/Media.php`

```php
<?php

namespace App\Models;

use App\Traits\Fieldable;
use App\Traits\HasTransformations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use Fieldable, HasTransformations, SoftDeletes;

    protected $fillable = [
        'library_id', 'name', 'file_name', 'original_name',
        'mime_type', 'disk', 'path', 'size',
        'alt_text', 'title', 'sort_order',
    ];

    protected $casts = [
        'size'       => 'integer',
        'sort_order' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class, 'library_id');
    }

    public function transformations(): HasMany
    {
        return $this->hasMany(Media\Transformation::class);
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
                    ->withTimestamps();
    }

    /**
     * Raw pivot rows for all field-driven references to this media item.
     * Returns columns: mediable_type, mediable_id, field_id,
     *                  field_name, field_handle, sort_order.
     *
     * Eloquent's morphedByMany cannot handle a pivot that references multiple
     * distinct model types cleanly, so we expose the raw query builder instead.
     */
    public function fieldUsages(): \Illuminate\Database\Query\Builder
    {
        return DB::table('mediables')
            ->where('media_id', $this->id)
            ->whereNotNull('field_id')
            ->join('fields', 'fields.id', '=', 'mediables.field_id')
            ->select('mediables.*', 'fields.name as field_name', 'fields.handle as field_handle');
    }

    public function isReferencedByField(): bool
    {
        return DB::table('mediables')
            ->where('media_id', $this->id)
            ->whereNotNull('field_id')
            ->exists();
    }

    // ── Storage helpers ────────────────────────────────────────────────────

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

---

## Step 6 — Create `App\Models\Media\Transformation`

**File:** `app/Models/Media/Transformation.php`

```php
<?php

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
        'params' => 'array',
        'size'   => 'integer',
        'width'  => 'integer',
        'height' => 'integer',
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

    public function markComplete(
        string $path,
        int    $size,
        ?int   $width  = null,
        ?int   $height = null
    ): void {
        $this->update(
            compact('path', 'size', 'width', 'height')
            + ['status' => 'complete', 'error' => null]
        );
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }
}
```

---

## Step 7 — Update `App\Models\Media\Library`

Replace the two inline relation methods (`category_groups()`, `field_groups()`)
with the existing project traits (`HasCategoryGroups`, `HasFieldGroups`). Add
`HasFieldLayout` and the new `HasMediaItems` trait (created in Step 9). Add
`field_layout_id` to `$fillable`.

> **Breaking change:** the inline methods were named `category_groups()` and
> `field_groups()` (snake_case). The traits expose `categoryGroups()` and
> `fieldGroups()` (camelCase). Every caller must be updated in **Step 15**.
> Do not skip Step 15 after applying this step.

**File:** `app/Models/Media/Library.php` *(full replacement)*

```php
<?php

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

    protected $table = 'media_libraries';   // must not be removed

    protected $fillable = [
        'field_layout_id', 'name', 'handle', 'adapter',
        'adapter_settings', 'allowed_types', 'max_size', 'sort_order',
    ];

    protected $casts = [
        'sort_order'       => 'integer',
        'adapter_settings' => 'array',
        'allowed_types'    => 'array',
        'max_size'         => 'integer',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(\App\Models\Media::class, 'library_id')
                    ->orderBy('sort_order');
    }
}
```

> `SoftDeletes` is intentionally omitted from `Library`. When a library is
> deleted the job (Step 21) soft-deletes its media items individually;
> `PurgeDeletedMedia` (Step 14) handles physical cleanup later.
>
> The old `categories()` helper on `Library` is removed. Callers that need
> categories should go through `$library->categoryGroups` and then each
> group's categories, consistent with how other models resolve them.

---

## Step 8 — Create `App\Traits\HasTransformations`

Lives on the `Media` model. Keeps transformation logic out of the model body
and decoupled from whichever image library is eventually chosen.

**File:** `app/Traits/HasTransformations.php`

```php
<?php

namespace App\Traits;

use App\Models\Media\Transformation;
use App\Services\Media\TransformationDriverInterface;
use Illuminate\Support\Facades\Storage;

trait HasTransformations
{
    public function getTransformation(string $key): ?Transformation
    {
        return $this->transformations()->where('key', $key)->first();
    }

    /** Returns the transformation only if it has completed successfully. */
    public function transformation(string $key): ?Transformation
    {
        $t = $this->getTransformation($key);
        return ($t && $t->isComplete()) ? $t : null;
    }

    public function hasTransformation(string $key): bool
    {
        return $this->transformation($key) !== null;
    }

    /**
     * Request a transformation. Returns immediately with a pending record
     * if the driver is async; or a complete record if sync.
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

        app(TransformationDriverInterface::class)->dispatch($transformation);

        return $transformation;
    }

    public function clearTransformation(string $key): void
    {
        $t = $this->getTransformation($key);
        if (!$t) {
            return;
        }
        if ($t->fileExists()) {
            Storage::disk($t->disk)->delete($t->path);
        }
        $t->delete();
    }

    public function clearTransformations(): void
    {
        foreach ($this->transformations as $t) {
            if ($t->fileExists()) {
                Storage::disk($t->disk)->delete($t->path);
            }
            $t->delete();
        }
    }

    protected function derivedPath(string $key, array $params = []): string
    {
        $dir  = dirname($this->path);
        $stem = pathinfo($this->file_name, PATHINFO_FILENAME);
        $ext  = $params['format'] ?? pathinfo($this->file_name, PATHINFO_EXTENSION);
        return $dir . '/_t/' . $stem . '_' . $key . '.' . $ext;
    }
}
```

---

## Step 9 — Create `App\Traits\HasMediaItems`

Lives on `Media\Library`. Handles upload validation, file storage, and deletion.
Physical file removal happens **only** in `purgeMedia()` — never on soft-delete.

**File:** `app/Traits/HasMediaItems.php`

```php
<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasMediaItems
{
    /**
     * Store an uploaded file and create a Media record.
     * sort_order is computed inside a transaction with a write lock to prevent
     * duplicate order values under concurrent uploads.
     *
     * @throws \InvalidArgumentException when the file fails library constraints.
     */
    public function addMediaFromUpload(UploadedFile $file, array $attributes = []): Media
    {
        $errors = $this->validateUpload($file);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        return DB::transaction(function () use ($file, $attributes) {
            $disk     = $this->adapter;
            $folder   = $this->handle;
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs($folder, $fileName, $disk);

            $nextOrder = (int) $this->media()->lockForUpdate()->max('sort_order') + 1;

            return $this->media()->create(array_merge([
                'uuid'          => (string) Str::uuid(),
                'name'          => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name'     => $fileName,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'disk'          => $disk,
                'path'          => $path,
                'size'          => $file->getSize(),
                'sort_order'    => $nextOrder,
            ], $attributes));
        });
    }

    /**
     * Soft-delete a media record. Physical file is NOT removed here.
     * PurgeDeletedMedia handles cleanup after the grace period.
     */
    public function removeMedia(Media $media): void
    {
        $media->delete();
    }

    /**
     * Permanently delete a media record and its physical file.
     * Called by the purge job, or directly when immediate removal is needed.
     */
    public function purgeMedia(Media $media): void
    {
        foreach ($media->transformations as $t) {
            Storage::disk($t->disk)->delete($t->path);
        }
        Storage::disk($media->disk)->delete($media->path);
        $media->forceDelete();
    }

    /**
     * Returns an array of human-readable error strings. Empty = valid.
     * The HTTP layer (UploadMediaRequest) also validates mime type and size
     * as a first line of defence; this provides a second check for uploads
     * that bypass the request layer.
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

---

## Step 10 — Create `App\Traits\HasMedia`

Goes on any model that holds media attachments: `Entry`, `User`, `Category`, etc.

**File:** `app/Traits/HasMedia.php`

```php
<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasMedia
{
    /** All media attached to this model via any attachment method. */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
                    ->withTimestamps()
                    ->withPivot('sort_order', 'field_id')
                    ->orderByPivot('sort_order');
    }

    /** Media attached directly (field_id IS NULL) — avatars, library browser picks. */
    public function directMedia(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
                    ->wherePivotNull('field_id')
                    ->withTimestamps()
                    ->withPivot('sort_order')
                    ->orderByPivot('sort_order');
    }

    /**
     * Media attached via a specific FileUpload field.
     *
     * Pass an int ID in batch/list contexts to avoid a per-call DB lookup.
     * Pass a string handle only in single-model contexts (edit forms, etc.).
     * once() memoises the handle→ID lookup within a single request.
     */
    public function mediaForField(string|int $field): MorphToMany
    {
        $fieldId = is_int($field)
            ? $field
            : once(fn() => \App\Models\Field::where('handle', $field)->value('id'));

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
            $media->id => ['sort_order' => $sortOrder, 'field_id' => null],
        ]);
    }

    /** Remove a media item from all pivot rows for this model. */
    public function detachMedia(Media $media): void
    {
        $this->media()->detach($media->id);
    }

    /** Replace direct attachments with exactly the given IDs. */
    public function syncMedia(array $mediaIds): void
    {
        $this->directMedia()->sync($mediaIds);
    }

    /**
     * First directly-attached media item, optionally scoped to a library handle.
     */
    public function firstMedia(?string $libraryHandle = null): ?Media
    {
        return $this->directMedia()
            ->when($libraryHandle, fn($q) => $q->whereHas(
                'library', fn($lq) => $lq->where('handle', $libraryHandle)
            ))
            ->first();
    }
}
```

---

## Step 11 — Create the Transformation Driver Interface and NullDriver

The interface is the single swap point for any image library. Wire the null
driver immediately so nothing breaks before a real library is chosen.

**File:** `app/Services/Media/TransformationDriverInterface.php`

```php
<?php

namespace App\Services\Media;

use App\Models\Media\Transformation;

interface TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void;
    public function applySync(Transformation $transformation): string;
    public function resize(int $width, int $height): static;
    public function fit(int $width, int $height): static;
    public function crop(int $width, int $height, int $x = 0, int $y = 0): static;
    public function quality(int $quality): static;
    public function format(string $format): static;
    public function sharpen(int $amount = 10): static;
    public function watermark(string $sourcePath, string $position = 'bottom-right'): static;
}
```

**File:** `app/Services/Media/NullTransformationDriver.php`

```php
<?php

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

    public function resize(int $width, int $height): static              { return $this; }
    public function fit(int $width, int $height): static                 { return $this; }
    public function crop(int $width, int $height, int $x = 0, int $y = 0): static { return $this; }
    public function quality(int $quality): static                        { return $this; }
    public function format(string $format): static                       { return $this; }
    public function sharpen(int $amount = 10): static                    { return $this; }
    public function watermark(string $sourcePath, string $position = 'bottom-right'): static { return $this; }
}
```

To adopt a real image library later: create one new class implementing the
interface and swap the binding in `AppServiceProvider::register()`. Nothing
else changes.

---

## Step 12 — Create `App\Services\MediaStorageService`

Controllers and actions call this. They should never call `Storage::` directly.

**File:** `app/Services/MediaStorageService.php`

```php
<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function upload(Library $library, UploadedFile $file, array $attributes = []): Media
    {
        return $library->addMediaFromUpload($file, $attributes);
    }

    /** Soft-delete — physical file is preserved until the purge job runs. */
    public function delete(Media $media): void
    {
        $media->library->removeMedia($media);
    }

    /** Hard-delete the record and physical file immediately. Use sparingly. */
    public function purge(Media $media): void
    {
        $media->library->purgeMedia($media);
    }

    public function url(Media $media, ?int $signedMinutes = null): string
    {
        return $signedMinutes !== null
            ? $media->temporaryUrl($signedMinutes)
            : $media->url();
    }

    public function disk(Media $media)
    {
        return Storage::disk($media->disk);
    }
}
```

---

## Step 13 — Register Bindings in `AppServiceProvider`

Open `app/Providers/AppServiceProvider.php`.

In `register()`, add:

```php
$this->app->bind(
    \App\Services\Media\TransformationDriverInterface::class,
    \App\Services\Media\NullTransformationDriver::class
);

$this->app->singleton('media-service', fn() => new \App\Services\MediaStorageService());
```

In `boot()`, add the observer registration. The morph map entries `'media'`
and `'media_library'` are already correct — leave them untouched:

```php
\App\Models\FieldValue::observe(\App\Observers\FieldValueObserver::class);
```

---

## Step 14 — Create `App\Jobs\PurgeDeletedMedia` and Schedule It

**File:** `app/Jobs/PurgeDeletedMedia.php`

```php
<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

class PurgeDeletedMedia implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(protected int $graceDays = 30) {}

    public function handle(): void
    {
        Media::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($this->graceDays))
            ->with('transformations')
            ->chunkById(100, function ($items) {
                foreach ($items as $media) {
                    foreach ($media->transformations as $t) {
                        if (Storage::disk($t->disk)->exists($t->path)) {
                            Storage::disk($t->disk)->delete($t->path);
                        }
                    }
                    if (Storage::disk($media->disk)->exists($media->path)) {
                        Storage::disk($media->disk)->delete($media->path);
                    }
                    $media->forceDelete();
                }
            });
    }
}
```

In `routes/console.php`, add alongside the existing `model:prune` schedule:

```php
use App\Jobs\PurgeDeletedMedia;

Schedule::job(new PurgeDeletedMedia(graceDays: 30))->daily();
```

---

## Step 15 — Update All Callers of the Renamed Library Relations

Step 7 changed `category_groups()` → `categoryGroups()` and
`field_groups()` → `fieldGroups()` on `Media\Library`. Three files use the
old snake_case names and must be updated before anything runs.

**`app/Actions/Media/Library/CreateNewMediaLibrary.php`**

```php
public function create(array $input): Library
{
    $library = Library::create($input);

    if (!empty($input['category_groups'])) {
        $library->categoryGroups()->sync($input['category_groups']);
    }

    return $library;
}
```

**`app/Actions/Media/Library/EditMediaLibrary.php`**

```php
public function edit(Library $library, array $input): bool
{
    $library->categoryGroups()->sync($input['category_groups'] ?? []);
    $library->fieldGroups()->sync($input['field_groups'] ?? []);

    return $library->update($input);
}
```

**`app/Http/Controllers/Admin/Media/Library.php`**

Change the two eager-load calls to camelCase (one in `create()`, one in
`edit()`):

```php
// Before
LibraryModel::with('category_groups')->find($id);

// After
LibraryModel::with('categoryGroups')->find($id);
```

---

## Step 16 — Fix `FieldValue::resolvedValue()`

The current implementation instantiates `fieldType->instance()` twice.
Replace the method body with a single-instantiation version.

**File:** `app/Models/FieldValue.php` — replace `resolvedValue()`:

```php
public function resolvedValue(): mixed
{
    $fieldType = $this->field?->fieldType;

    if (!$fieldType) {
        return $this->value_text;
    }

    $instance = $fieldType->instance();
    $column   = $instance->storageColumn();

    // If the field type defines its own value(), delegate to it.
    // FileUpload::value() returns Collection<Media> instead of raw IDs.
    // All other existing types fall back to AbstractField::value() which
    // is a transparent pass-through, so this branch is always safe.
    if (method_exists($instance, 'value')) {
        return $instance->value($this->{$column});
    }

    return $this->{$column};
}
```

---

## Step 17 — Create `App\Field\Types\FileUpload`

**File:** `app/Field/Types/FileUpload.php`

```php
<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Models\Media;
use Illuminate\Support\Collection;

class FileUpload extends AbstractField
{
    protected string $handle = 'file_upload';
    protected string $name   = 'File Upload';

    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function isRelational(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool|string
    {
        $ids = $this->normaliseIds($value);
        $min = (int) $this->getSetting('min', 0);
        $max = $this->getSetting('max');

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

    /** Cast raw stored JSON to a plain array of integer IDs. */
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
     * Called by FieldValue::resolvedValue() so $entry->field('gallery')
     * returns Collection<Media>, never raw IDs.
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

    public function normaliseIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->pluck('id')->map('intval')->all();
        }
        return $this->cast($value);
    }
}
```

**Available field settings:**

| Key | Type | Default | Purpose |
|---|---|---|---|
| `library_id` | int\|null | null | Restrict picker to a specific library |
| `library_handle` | string\|null | null | Portable alternative to `library_id` |
| `min` | int | 0 | Minimum files required |
| `max` | int\|null | null | Maximum files (null = unlimited; 1 = single-file mode) |
| `allowed_types` | array\|null | null | Override library MIME types at field level |
| `show_preview` | bool | true | Render inline file preview in the UI |

---

## Step 18 — Create `App\Observers\FieldValueObserver`

Single point that keeps the `mediables` pivot truthful for FileUpload fields.
Nothing else should write field-scoped rows to that pivot.

`Field::$with = ['fieldType']` is already set, and `FieldValue::$with =
['field']` chains through it, so `$fieldValue->field->fieldType` is always
loaded — no N+1 on the `isFileUpload()` check.

**File:** `app/Observers/FieldValueObserver.php`

```php
<?php

namespace App\Observers;

use App\Field\Types\FileUpload;
use App\Models\FieldValue;
use Illuminate\Support\Facades\DB;

class FieldValueObserver
{
    public function saved(FieldValue $fieldValue): void
    {
        if (!$this->isFileUpload($fieldValue)) {
            return;
        }
        $this->syncMediables($fieldValue);
    }

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

    private function isFileUpload(FieldValue $fieldValue): bool
    {
        return $fieldValue->field?->fieldType?->object === FileUpload::class;
    }

    private function syncMediables(FieldValue $fieldValue): void
    {
        $type    = $fieldValue->fieldable_type;
        $id      = $fieldValue->fieldable_id;
        $fieldId = $fieldValue->field_id;

        $instance = $fieldValue->field->fieldType->instance();
        $newIds   = $instance->cast($fieldValue->value_json);

        // Remove pivot rows no longer in the selection
        DB::table('mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id',   $id)
            ->where('field_id',      $fieldId)
            ->whereNotIn('media_id', $newIds ?: [0])
            ->delete();

        // Upsert current selection, preserving sort order
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

---

## Step 19 — Create `App\Actions\Media\DeleteMedia`

**File:** `app/Actions/Media/DeleteMedia.php`

```php
<?php

namespace App\Actions\Media;

use App\Actions\AbstractAction;
use App\Models\Media;

class DeleteMedia extends AbstractAction
{
    public function delete(Media $media): void
    {
        app('media-service')->delete($media);
    }
}
```

---

## Step 20 — Replace `App\Actions\Media\Library\UploadMedia`

Remove the Spatie `addMedia()->toMediaCollection()` call entirely.

**File:** `app/Actions/Media/Library/UploadMedia.php`

```php
<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Http\Requests\FormRequest;
use App\Models\Media;
use App\Models\Media\Library as LibraryModel;

class UploadMedia extends AbstractAction
{
    public function upload(FormRequest $request, LibraryModel $library): Media
    {
        $media = app('media-service')->upload($library, $request->file('file'), [
            'name' => $request->input('name'),
        ]);

        if (!empty($request->input('categories'))) {
            $media->categories()->sync($request->input('categories'));
        }

        return $media;
    }
}
```

---

## Step 21 — Implement `ProcessMediaLibraryRemoval` and Update `DeleteMediaLibrary`

**File:** `app/Jobs/ProcessMediaLibraryRemoval.php` *(full replacement)*

```php
<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessMediaLibraryRemoval implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(protected int $libraryId) {}

    public function handle(): void
    {
        // Soft-delete all media belonging to this library.
        // Physical file removal is handled by PurgeDeletedMedia on its next run.
        // To remove files immediately instead, swap $media->delete() for
        // $library->purgeMedia($media) after loading the library model.
        Media::where('library_id', $this->libraryId)
            ->whereNull('deleted_at')
            ->chunkById(100, function ($items) {
                foreach ($items as $media) {
                    $media->delete();
                }
            });
    }
}
```

**File:** `app/Actions/Media/Library/DeleteMediaLibrary.php`

```php
<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Jobs\ProcessMediaLibraryRemoval;
use App\Models\Media\Library;

class DeleteMediaLibrary extends AbstractAction
{
    public function delete(Library $library): bool
    {
        $libraryId = $library->id;
        $deleted   = $library->delete();

        if ($deleted) {
            ProcessMediaLibraryRemoval::dispatch($libraryId);
        }

        return (bool) $deleted;
    }
}
```

---

## Step 22 — Fix `StoreMediaLibraryFormRequest`

The request validates `'storage'` but the database column is `'adapter'`. No
Blade views for media library forms exist yet, so only the PHP file changes.

**File:** `app/Http/Requests/Media/Library/StoreMediaLibraryFormRequest.php`

In `rules()`:

```php
// Before
'storage' => ['required', 'string'],

// After
'adapter' => ['required', 'string'],
```

In the conditional block:

```php
// Before
if ($this->data('storage') == 'local') {

// After
if ($this->data('adapter') == 'local') {
```

When media library Blade views are eventually created, use `name="adapter"`
throughout.

---

## Step 23 — Update `App\Models\User`

Add `HasMedia` and update `avatar()` to check the media library first.
`laravolt/avatar` is already in `composer.json` — no new dependency.

In `app/Models/User.php`, add the import and trait, then add/replace:

```php
use App\Models\Media;
use App\Traits\HasMedia;

// Add HasMedia to the use statement:
use HasFactory, Notifiable, HasApiTokens, HasRoles, Fieldable, TwoFactorAuthenticatable, HasMedia;

public function avatar(): string
{
    $media = $this->firstMedia('avatars');

    if ($media) {
        return $media->url();
    }

    // Fallback: generated avatar from name; no external HTTP request.
    return \Laravolt\Avatar\Facade::create($this->name)->toBase64();
}

public function setAvatar(Media $media): void
{
    // Remove any existing avatar before attaching the new one.
    $existing = $this->directMedia()
        ->whereHas('library', fn($q) => $q->where('handle', 'avatars'))
        ->get();

    foreach ($existing as $old) {
        $this->detachMedia($old);
    }

    $this->attachMedia($media);
}
```

---

## Step 24 — Seeders

### 24a — FieldTypeSeeder

Add to the `$types` array in `database/seeders/FieldTypeSeeder.php`:

```php
['name' => 'File Upload', 'object' => \App\Field\Types\FileUpload::class],
```

### 24b — Avatars Library

Add to a media seeder or `DatabaseSeeder`:

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

---

## Step 25 — Tests

Run the full suite first to confirm baseline:

```bash
composer test
```

Minimum new test coverage required:

```php
// Upload creates a Media record and stores the physical file
Storage::fake('local');
$library = MediaLibrary::factory()->create(['adapter' => 'local']);
$file    = UploadedFile::fake()->image('photo.jpg');
$media   = $library->addMediaFromUpload($file);
expect($media->file_name)->toEndWith('.jpg');
expect($media->path)->not->toBeEmpty();
Storage::disk('local')->assertExists($media->path);

// Soft-delete preserves the file; purge removes it
$library->removeMedia($media);
expect($media->fresh()->trashed())->toBeTrue();
Storage::disk('local')->assertExists($media->path);
$library->purgeMedia($media->fresh());
Storage::disk('local')->assertMissing($media->path);

// FileUpload::value() resolves IDs to Collection<Media>
$images = Media::factory()->count(3)->for($library)->create();
$field  = Field::factory()->fileUpload()->create();
$entry  = Entry::factory()->create();
FieldValue::create([
    'field_id'       => $field->id,
    'fieldable_id'   => $entry->id,
    'fieldable_type' => 'entry',
    'value_json'     => $images->pluck('id')->toJson(),
]);
$resolved = $entry->fresh(['fieldValues.field.fieldType'])->field($field->handle);
expect($resolved)->toHaveCount(3);
expect($resolved->first())->toBeInstanceOf(Media::class);

// FieldValueObserver syncs the mediables pivot on save
expect(DB::table('mediables')->where('field_id', $field->id)->count())->toBe(3);

// Library deletion dispatches the removal job
Queue::fake();
(new DeleteMediaLibrary)->delete($library);
Queue::assertPushed(ProcessMediaLibraryRemoval::class);
```

---

## Completion Checklist

Work through these in order. Each item is a discrete, independently verifiable state.

- [ ] **Step 1** — `media` table: `collection_name` and `order_column` dropped; `library_id`, `original_name`, `path`, `alt_text`, `title`, `sort_order`, `deleted_at` added; migration passes cleanly
- [ ] **Step 2** — `mediables` table exists with `field_id` nullable FK and four-column unique constraint
- [ ] **Step 3** — `media_transformations` table exists
- [ ] **Step 4** — `field_layout_id` column on `media_libraries`; `php artisan migrate` passes
- [ ] **Step 5** — `Media` model: no Spatie import; `library()` BelongsTo present; `categories()` MorphToMany preserved; `fieldUsages()`, `isReferencedByField()`, and storage helpers present
- [ ] **Step 6** — `Transformation` model exists; `markComplete()` accepts nullable `$width`/`$height`
- [ ] **Step 7** — `Library` uses `HasCategoryGroups`, `HasFieldGroups`, `HasFieldLayout`, `HasMediaItems`; inline `category_groups()` and `field_groups()` methods gone; `field_layout_id` in `$fillable`
- [ ] **Step 8** — `HasTransformations` trait created
- [ ] **Step 9** — `HasMediaItems` trait created; sort order uses `lockForUpdate()`
- [ ] **Step 10** — `HasMedia` trait created; `mediaForField()` uses `once()` for string handle lookup
- [ ] **Step 11** — `TransformationDriverInterface` and `NullTransformationDriver` in `app/Services/Media/`
- [ ] **Step 12** — `MediaStorageService` in `app/Services/`
- [ ] **Step 13** — `AppServiceProvider::register()` has driver binding and `media-service` singleton; `boot()` registers `FieldValueObserver`; morph map unchanged
- [ ] **Step 14** — `PurgeDeletedMedia` job created; `Schedule::job(new PurgeDeletedMedia(...))->daily()` in `routes/console.php`
- [ ] **Step 15** — `CreateNewMediaLibrary` uses `categoryGroups()->sync()`; `EditMediaLibrary` uses `categoryGroups()->sync()` and `fieldGroups()->sync()`; `Admin\Media\Library` controller eager-loads `categoryGroups` (camelCase)
- [ ] **Step 16** — `FieldValue::resolvedValue()` instantiates `instance()` once; uses `method_exists` guard
- [ ] **Step 17** — `FileUpload` field type created; `value()` returns `Collection<Media>`
- [ ] **Step 18** — `FieldValueObserver` created; registered in `AppServiceProvider::boot()`
- [ ] **Step 19** — `DeleteMedia` action created
- [ ] **Step 20** — `UploadMedia` action uses `media-service`; no Spatie calls remain
- [ ] **Step 21** — `ProcessMediaLibraryRemoval` takes `int $libraryId` constructor arg and implements `handle()`; `DeleteMediaLibrary` dispatches it
- [ ] **Step 22** — `StoreMediaLibraryFormRequest` validates `'adapter'` throughout
- [ ] **Step 23** — `User` has `HasMedia`; `avatar()` checks `firstMedia('avatars')` before Laravolt fallback; `setAvatar()` added
- [ ] **Step 24** — `FileUpload` row in `FieldTypeSeeder`; avatars library seeded
- [ ] **Step 25** — `composer test` passes; upload, purge, field-resolution, observer, and library-deletion tests added
