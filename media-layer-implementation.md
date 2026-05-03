# Media Layer — Implementation Plan (Clean Slate)

*Written 2026-05-02. Assumes the Spatie media library has been removed and the `Media`
model is a blank class. `Media\Library`, its table, actions, and form requests are
already in place. All known plan bugs are corrected inline — no separate errata.*

---

## Starting Conditions

**Already exists — do not touch yet:**

- `app/Models/Media/Library.php` — keep `$table = 'media_libraries'` and all current columns
- `database/migrations/2025_12_27_160903_create_media_library_table.php`
- `app/Actions/Media/Library/` — all four actions (will update two of them in later steps)
- `app/Http/Requests/Media/Library/` — all four requests (will fix one in a later step)
- `app/Traits/HasFieldLayout.php`, `HasFieldGroups.php`, `HasCategoryGroups.php`
- Field system: `AbstractField`, `FieldValue`, `Field`, `Field\Type`, `Fieldable` trait
- `AppServiceProvider` morph map — `'media'` and `'media_library'` keys must be preserved

**Assumed gone (clean slate):**

- `spatie/laravel-medialibrary` and `spatie/laravel-tags` removed from `composer.json`
- Old Spatie `media` table columns (`model_type`, `model_id`, `collection_name`,
  `conversions_disk`, `manipulations`, `generated_conversions`, `responsive_images`,
  `order_column`, `custom_properties`) — these will be replaced in Step 1
- `app/Models/Media.php` — replaced from scratch in Step 5

**Do not start two steps in parallel** — several steps have migrations and models that
depend on each other. Follow the order below.

---

## Step 1 — Rewrite the `media` Table

Create a new migration. Because the Spatie schema is gone, this drops the Spatie-specific
columns and replaces them with the native schema. Run this before touching any model code.

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
            // Remove Spatie columns
            $table->dropColumn([
                'model_type', 'model_id', 'collection_name',
                'conversions_disk', 'manipulations', 'custom_properties',
                'generated_conversions', 'responsive_images', 'order_column',
            ]);

            // Add native columns
            $table->string('original_name')->after('file_name');
            $table->string('path')->after('disk');
            $table->string('alt_text')->nullable()->after('path');
            $table->string('title')->nullable()->after('alt_text');
            $table->unsignedInteger('sort_order')->default(0)->after('title');
            $table->softDeletes()->after('updated_at');
        });

        // The uuid and library_id columns already exist from the Spatie migration.
        // The library_id FK was added in 2025_12_27_160903 — nothing to do here.
    }

    public function down(): void
    {
        // Reverting this migration in production is destructive.
        // Restore Spatie columns manually if needed.
    }
};
```

> **Note:** If standing up a completely fresh database (e.g. in testing), you can instead
> rewrite `2025_12_26_134324_create_media_table.php` directly to contain only the native
> columns shown below. That is cleaner for green-field environments.
>
> Final `media` table columns: `id`, `uuid` (nullable unique), `library_id` (FK → media_libraries
> nullOnDelete), `name`, `file_name`, `original_name`, `mime_type`, `disk`, `path`, `size`,
> `alt_text`, `title`, `sort_order`, `deleted_at`, `created_at`, `updated_at`.

---

## Step 2 — Create the `mediables` Pivot Table

This is the many-to-many link between any model and the media it references.
One file can be attached to many models, and one model can hold many files.

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
            $table->morphs('mediable');                  // mediable_type, mediable_id
            $table->foreignId('field_id')                // null = direct attach; N = via FileUpload field
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

> `field_id = null` means the media was attached directly (avatar upload, library browser).
> `field_id = N` means it was attached through a specific FileUpload field on that model.
> This distinction allows `mediaForField('gallery')` and `directMedia()` to coexist
> without collision.

---

## Step 3 — Create the `media_transformations` Table

Stores derived image variants (thumbnails, crops, etc.). The transformation library is
not chosen yet — this table is library-agnostic.

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

            $table->string('key');                        // e.g. 'thumbnail', 'hero_2x'
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('params')->nullable();           // driver-agnostic params
            $table->string('driver')->nullable();         // set once library is chosen

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

Allows a library to have a custom field layout (same pattern as EntryGroup).

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
            $table->dropForeignIdFor(\App\Models\FieldLayout::class);
            $table->dropColumn('field_layout_id');
        });
    }
};
```

**Run all four migrations now:**

```bash
php artisan migrate
```

---

## Step 5 — Create `App\Models\Media`

Fresh model. No Spatie, no `HasTags`. Follows the same structural pattern as `Entry`.

**File:** `app/Models/Media.php`

```php
<?php

namespace App\Models;

use App\Traits\Fieldable;
use App\Traits\Category\HasCategories;
use App\Traits\HasTransformations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    // ── Relationships ──────────────────────────────────────────────────────

    public function library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class, 'library_id');
    }

    public function transformations(): HasMany
    {
        return $this->hasMany(Media\Transformation::class);
    }

    /**
     * All models that reference this media item.
     *
     * NOTE: Eloquent's morphedByMany cannot handle a pivot that references
     * multiple distinct model types. Do NOT implement a morphedByMany() here.
     * Callers needing usage data should use MediaUsageRepository::forMedia($this),
     * which groups rows by mediable_type and eager-loads each group separately.
     * The raw pivot can also be queried directly:
     *
     *   DB::table('mediables')->where('media_id', $this->id)->get()
     */
    // usages() intentionally omitted — see note above.

    // ── Field-scoped usage (added in Step 18) ─────────────────────────────
    // fieldUsages() and isReferencedByField() are added after the FileUpload
    // field type and mediables pivot are fully wired up.

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

    /**
     * @param  ?int  $width   Nullable — not all transformations produce explicit dimensions.
     * @param  ?int  $height  Nullable — same reason.
     */
    public function markComplete(string $path, int $size, ?int $width = null, ?int $height = null): void
    {
        $this->update(compact('path', 'size', 'width', 'height') + ['status' => 'complete', 'error' => null]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }
}
```

---

## Step 7 — Update `App\Models\Media\Library`

Replace Spatie's `InteractsWithMedia` / `HasMedia` interface with the native traits.
Preserve `$table = 'media_libraries'` — without it Eloquent defaults to `libraries`.

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

    protected $table = 'media_libraries';   // ← must not be dropped

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

> `activeMedia()` is intentionally omitted. `SoftDeletes` on `Media` already adds a
> global scope excluding deleted rows — a redundant `whereNull('deleted_at')` on top
> would be misleading. If you need deleted rows, call `$library->media()->withTrashed()`.

---

## Step 8 — Create `App\Traits\HasTransformations`

Lives on the `Media` model. Stubs transformation requests without binding to a
concrete image library — the driver is swapped in Step 11.

**File:** `app/Traits/HasTransformations.php`

```php
<?php

namespace App\Traits;

use App\Models\Media\Transformation;
use App\Services\Media\TransformationDriverInterface;

trait HasTransformations
{
    public function getTransformation(string $key): ?Transformation
    {
        return $this->transformations()->where('key', $key)->first();
    }

    public function transformation(string $key): ?Transformation
    {
        $t = $this->getTransformation($key);
        return ($t && $t->isComplete()) ? $t : null;
    }

    public function hasTransformation(string $key): bool
    {
        return $this->transformation($key) !== null;
    }

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
        if (!$t) return;
        if ($t->fileExists()) {
            \Illuminate\Support\Facades\Storage::disk($t->disk)->delete($t->path);
        }
        $t->delete();
    }

    public function clearTransformations(): void
    {
        foreach ($this->transformations as $t) {
            if ($t->fileExists()) {
                \Illuminate\Support\Facades\Storage::disk($t->disk)->delete($t->path);
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

Lives on `Media\Library`. Handles upload, soft-delete, and purge. Physical file removal
happens only in `purgeMedia()` — never on soft-delete.

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
     * @throws \InvalidArgumentException on constraint violation
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
     * PurgeDeletedMedia job handles cleanup after the grace period.
     */
    public function removeMedia(Media $media): void
    {
        $media->delete();
    }

    /**
     * Permanently delete a media record and its physical file.
     * Called by the purge job, or directly when you are certain.
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

---

## Step 10 — Create `App\Traits\HasMedia`

Goes on any model that needs to hold media attachments: `User`, `Entry`, `Category`, etc.

**File:** `app/Traits/HasMedia.php`

```php
<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasMedia
{
    /**
     * All media attached to this model (any attachment method).
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
                    ->withTimestamps()
                    ->withPivot('sort_order', 'field_id')
                    ->orderByPivot('sort_order');
    }

    /**
     * Media attached directly (field_id IS NULL) — avatars, library browser picks, etc.
     */
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
     * Pass a field ID (int) in batch/list contexts to avoid a per-call DB lookup.
     * Pass a handle (string) only in single-model contexts (edit forms, etc.).
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

    public function attachMedia(Media $media, int $sortOrder = 0): void
    {
        $this->directMedia()->syncWithoutDetaching([
            $media->id => ['sort_order' => $sortOrder, 'field_id' => null],
        ]);
    }

    public function detachMedia(Media $media): void
    {
        $this->media()->detach($media->id);
    }

    public function syncMedia(array $mediaIds): void
    {
        $this->directMedia()->sync($mediaIds);
    }

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

The interface is the single swap point for any image library. Wire the null driver
immediately so nothing breaks before a concrete driver is chosen.

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

    public function resize(int $width, int $height): static   { return $this; }
    public function fit(int $width, int $height): static      { return $this; }
    public function crop(int $width, int $height, int $x = 0, int $y = 0): static { return $this; }
    public function quality(int $quality): static             { return $this; }
    public function format(string $format): static            { return $this; }
    public function sharpen(int $amount = 10): static         { return $this; }
    public function watermark(string $sourcePath, string $position = 'bottom-right'): static { return $this; }
}
```

When a real image library is chosen: create one new class implementing the interface,
swap the binding in `AppServiceProvider` (Step 12). Nothing else changes.

---

## Step 12 — Create `App\Services\MediaStorageService`

Controllers and actions call this — they never call `Storage::` directly.

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

## Step 13 — Register Bindings in AppServiceProvider

Open `app/Providers/AppServiceProvider.php` and add inside `register()`:

```php
$this->app->bind(
    \App\Services\Media\TransformationDriverInterface::class,
    \App\Services\Media\NullTransformationDriver::class
);

$this->app->singleton('media-service', fn() => new \App\Services\MediaStorageService());
```

Confirm the morph map in `boot()` still contains both media keys:

```php
Relation::morphMap([
    // ... existing entries ...
    'media'         => \App\Models\Media::class,
    'media_library' => \App\Models\Media\Library::class,
]);
```

Also register the observer in `boot()` — do it now so it is not forgotten:

```php
\App\Models\FieldValue::observe(\App\Observers\FieldValueObserver::class);
```

---

## Step 14 — Create `App\Jobs\PurgeDeletedMedia`

Runs on a schedule. Permanently removes files and records soft-deleted beyond the
grace period.

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

**Register the schedule** in `routes/console.php` (Laravel 11+):

```php
use App\Jobs\PurgeDeletedMedia;

Schedule::job(new PurgeDeletedMedia(graceDays: 30))->daily();
```

---

## Step 15 — Fix `FieldValue::resolvedValue()`

`app/Models/FieldValue.php` currently ends mid-method after
`$column = $fieldType->instance()->storageColumn();` — the body is incomplete and
the method has never returned a value. Replace the full method body:

**File:** `app/Models/FieldValue.php` — update `resolvedValue()`:

```php
public function resolvedValue(): mixed
{
    $fieldType = $this->field?->fieldType;

    if (!$fieldType) {
        return $this->value_text;
    }

    $instance = $fieldType->instance();
    $column   = $instance->storageColumn();

    // If the field type defines value(), delegate to it. This is how FileUpload
    // returns Collection<Media> instead of the raw [3, 7, 12] ID array.
    if (method_exists($instance, 'value')) {
        return $instance->value($this->{$column});
    }

    return $this->{$column};
}
```

---

## Step 16 — Create `App\Field\Types\FileUpload`

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

**Field settings:**

| Key | Type | Default | Purpose |
|---|---|---|---|
| `library_id` | int\|null | null | Restrict picker to a specific library by ID |
| `library_handle` | string\|null | null | Portable alternative to `library_id` |
| `min` | int | 0 | Minimum files required |
| `max` | int\|null | null | Maximum files (null = unlimited; 1 = single-file mode) |
| `allowed_types` | array\|null | null | Override library MIME types at field level |
| `show_preview` | bool | true | Render inline file preview in the UI |

---

## Step 17 — Create `App\Observers\FieldValueObserver`

Single point responsible for keeping the `mediables` pivot truthful for FileUpload
fields. Nothing else should write field-scoped rows to that pivot.

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

        // Remove pivot rows that are no longer in the selection
        DB::table('mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id',   $id)
            ->where('field_id',      $fieldId)
            ->whereNotIn('media_id', $newIds ?: [0])
            ->delete();

        // Upsert rows for the current selection, preserving sort order
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

The observer is already registered in `AppServiceProvider::boot()` from Step 13.

---

## Step 18 — Add Field-Scoped Methods to `App\Models\Media`

Now that the `mediables` pivot and `FileUpload` type exist, add the inverse usage
queries. Open `app/Models/Media.php` and add:

```php
use Illuminate\Support\Facades\DB;

/**
 * Raw pivot rows for all field-driven references to this media item.
 * Returns columns: mediable_type, mediable_id, field_id, field_name, field_handle, sort_order.
 *
 * Use this instead of a morphedByMany relationship — Eloquent cannot handle a pivot
 * that references multiple distinct model types cleanly.
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

## Step 20 — Update `App\Actions\Media\Library\UploadMedia`

Replace the old Spatie `addMedia()->toMediaCollection()` call.

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

## Step 21 — Implement `App\Jobs\ProcessMediaLibraryRemoval`

The job currently has an empty `handle()`. Implement it and update `DeleteMediaLibrary`
to pass the library ID and dispatch it.

**Deletion policy:** soft-delete all media belonging to the library. Physical files are
cleaned up by `PurgeDeletedMedia` during its next scheduled run (grace period applies).
To purge files immediately instead, replace `$media->delete()` with
`$library->purgeMedia($media)`.

**File:** `app/Jobs/ProcessMediaLibraryRemoval.php`

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
        Media::withTrashed()
            ->where('library_id', $this->libraryId)
            ->whereNull('deleted_at')
            ->chunkById(100, function ($items) {
                foreach ($items as $media) {
                    $media->delete(); // SoftDeletes — PurgeDeletedMedia cleans files later
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

The form request validates `'storage'` but the database column is `'adapter'`.

**File:** `app/Http/Requests/Media/Library/StoreMediaLibraryFormRequest.php`

In `rules()`, change:

```php
// Before
'storage' => ['required', 'string'],

// After
'adapter' => ['required', 'string'],
```

In the conditional block, change:

```php
// Before
if ($this->data('storage') == 'local') {

// After
if ($this->data('adapter') == 'local') {
```

Also update the create/edit Blade views to use `name="adapter"` wherever they
currently use `name="storage"`.

---

## Step 23 — Update `App\Models\User`

Add `HasMedia` so users can hold avatar attachments. Update `avatar()` to check the
media library before generating a fallback.

> **Behavioral change notice:** the current fallback is a Gravatar URL keyed on
> `$this->email`. The new fallback is a Laravolt-generated base64 image keyed on
> `$this->name`, with no external HTTP request. Both the `src` type and the fallback
> key change. Notify frontend and API consumers before deploying this step.

**In `app/Models/User.php`** — add the import, the trait, and replace `avatar()`:

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

    // Fallback: generated avatar from name; no external request.
    // Previously this was Gravatar keyed on $this->email — a behaviour change.
    return \Laravolt\Avatar\Facade::create($this->name)->toBase64();
}

public function setAvatar(Media $media): void
{
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

Add to `database/seeders/FieldTypeSeeder.php`:

```php
['name' => 'File Upload', 'object' => \App\Field\Types\FileUpload::class],
```

### 24b — Avatars library

Add to a media seeder (or `DatabaseSeeder`):

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

### 24c — Field groups for FileUpload fields *(recommended)*

See `MediaFieldGroupSeeder` in `media-refactor-plan.md` for a full seeder that creates
`hero_image`, `gallery`, `attachments`, `profile_photo`, `category_image`, and
`category_icon` fields across the four model contexts.

---

## Step 25 — Tests

Run the full suite to confirm nothing regressed:

```bash
composer test
```

Minimum new test coverage required for this layer:

```php
// Upload creates Media record and stores physical file
Storage::fake('local');
$library = MediaLibrary::factory()->create(['adapter' => 'local']);
$file    = UploadedFile::fake()->image('photo.jpg');
$media   = $library->addMediaFromUpload($file);
expect($media->file_name)->toEndWith('.jpg');
Storage::disk('local')->assertExists($media->path);

// Soft-delete preserves file; purge removes it
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

// Observer syncs mediables pivot on save
expect(DB::table('mediables')->where('field_id', $field->id)->count())->toBe(3);

// Library deletion dispatches removal job
Queue::fake();
(new DeleteMediaLibrary)->delete($library);
Queue::assertPushed(ProcessMediaLibraryRemoval::class);
```

---

## Completion Checklist

Work through these in order. Each item is a discrete, independently verifiable state.

- [ ] **Step 1** — `media` table: Spatie columns dropped; `original_name`, `path`, `alt_text`, `title`, `sort_order`, `deleted_at` present
- [ ] **Step 2** — `mediables` table exists with `field_id` column and four-column unique constraint
- [ ] **Step 3** — `media_transformations` table exists
- [ ] **Step 4** — `field_layout_id` column on `media_libraries`; `php artisan migrate` passes
- [ ] **Step 5** — `Media` extends `Model`, not `BaseMedia`; no Spatie imports
- [ ] **Step 6** — `Transformation` model exists; `markComplete()` uses `?int $width`, `?int $height`
- [ ] **Step 7** — `Library` uses `HasMediaItems`, no Spatie traits; `$table = 'media_libraries'` present
- [ ] **Step 8** — `HasTransformations` trait created
- [ ] **Step 9** — `HasMediaItems` trait created; sort_order increment uses `lockForUpdate()`
- [ ] **Step 10** — `HasMedia` trait created; `mediaForField()` uses `once()` for handle lookup
- [ ] **Step 11** — `TransformationDriverInterface` and `NullTransformationDriver` created
- [ ] **Step 12** — `MediaStorageService` created
- [ ] **Step 13** — `AppServiceProvider` registers driver binding, service singleton, observer, and morph map entries
- [ ] **Step 14** — `PurgeDeletedMedia` job created and scheduled
- [ ] **Step 15** — `FieldValue::resolvedValue()` is complete; dispatches to `$instance->value()` when available
- [ ] **Step 16** — `FileUpload` field type created; uses `value()`, not `resolve()`
- [ ] **Step 17** — `FieldValueObserver` created and registered
- [ ] **Step 18** — `Media::fieldUsages()` and `isReferencedByField()` added
- [ ] **Step 19** — `DeleteMedia` action created
- [ ] **Step 20** — `UploadMedia` action uses `media-service`, not Spatie API
- [ ] **Step 21** — `ProcessMediaLibraryRemoval::handle()` implemented; `DeleteMediaLibrary` dispatches it with library ID
- [ ] **Step 22** — `StoreMediaLibraryFormRequest` uses `'adapter'` throughout; Blade views updated
- [ ] **Step 23** — `User` uses `HasMedia`; `avatar()` updated; frontend/API consumers notified
- [ ] **Step 24** — `FileUpload` row in `FieldTypeSeeder`; avatars library seeded
- [ ] **Step 25** — `composer test` passes; upload, purge, field-resolution, and observer tests added
- [ ] **Final** — `composer remove spatie/laravel-medialibrary`; `config/media-library.php` deleted

> **Before the final step:** `App\Models\User` currently uses `HasTags` from
> `spatie/laravel-tags`. Removing that package without a replacement will cause a fatal
> error on `User` model load. Decide on a replacement (native `tags` table + `Taggable`
> trait, or a community package) and update `User` before running the remove command.
