# Media Layer — Implementation Plan

*Revised 2026-05-07. Fresh-install assumption: existing migration files are
rewritten in place; no ALTER TABLE migrations. All descriptions reflect the
literal current state of the codebase.*

---

## Actual Starting State

### Database — existing migration files

| File | Creates | Notes |
|---|---|---|
| `2025_12_26_134324_create_media_table.php` | `media` | Has Spatie remnants; **rewrite in place** |
| `2025_12_27_152812_create_tag_tables.php` | tag tables | Spatie Tags — package removed; **delete this file** |
| `2025_12_27_160903_create_media_library_table.php` | `media_libraries` | Missing `field_layout_id`; **rewrite in place** |

No `mediables` or `media_transformations` tables exist.

### Migration ordering constraint (important)

Laravel runs migrations in filename-timestamp order. The current sequence is:

```
2025_12_26  create_media_table          ← runs before media_libraries exists
2025_12_27  create_media_library_table  ← runs before field_layouts exists (Apr 2026)
2026_04_18  create_field_layouts_table
...
2026_04_18  create_category_groupables_table  ← last of the core tables
2026_04_28  create_entry_metrics_table
```

This means:
- `media.library_id` **cannot** carry a FK constraint in its own migration
  (media_libraries does not exist yet at that timestamp)
- `media_libraries.field_layout_id` **cannot** carry a FK constraint in its
  own migration (field_layouts does not exist until April 2026)
- New tables that FK into `fields` or `media` must come after `2026_04_14`

**Solution:** rewrite the two existing migrations with plain `unsignedBigInteger`
columns (no FK constraints) and add all deferred FK constraints in one new
migration that runs last.

### Models

`app/Models/Media.php` — extends `Model`; dead Spatie import still present;
`media_library()` relation (wrong name, no FK column); `categories()` MorphToMany.

`app/Models/Media/Library.php` — no Spatie; `$table = 'media_libraries'` set;
inline **snake_case** relations `category_groups()` and `field_groups()` defined
directly (not via traits); manual `categories()` helper.

### Traits in `app/Traits/` that already exist
`Fieldable`, `HasCategoryGroups`, `HasFieldGroups`, `HasFieldLayout`,
`HasEntryTree`, `PersistsFieldValues`, `PasswordValidationRules`.

### Traits that do not exist yet
`HasTransformations`, `HasMediaItems`, `HasMedia`.

### Callers that use snake_case relations on `Library` (updated in Step 6b)

| File | Lines to update |
|---|---|
| `Actions/Media/Library/CreateNewMediaLibrary.php` | `category_groups()->attach()` → `categoryGroups()->sync()` |
| `Actions/Media/Library/EditMediaLibrary.php` | both `category_groups()` and `field_groups()` calls → camelCase + `sync()` |
| `Http/Controllers/Admin/Media/Library.php` | `with('category_groups')` → `with('categoryGroups')` |

These are updated in Step 6b alongside the Library model rewrite so the
codebase is consistent from the start. No aliases needed on the model.

### Broken files that must be fixed (not optional)

| File | Problem |
|---|---|
| `Actions/Media/Library/UploadMedia.php` | Calls `$library->addMedia()->toMediaCollection()` — Spatie API, package gone; fatal at runtime |
| `Jobs/ProcessMediaLibraryRemoval.php` | `handle()` is empty; library deletion silently does nothing |
| `Actions/Media/Library/DeleteMediaLibrary.php` | Never dispatches `ProcessMediaLibraryRemoval`; has `@todo` saying so |
| `Http/Requests/Media/Library/StoreMediaLibraryFormRequest.php` | Validates key `'storage'`; DB column is `'adapter'` |

---

## Known Problems (highlighted before you start)

**1. `FieldValue::resolvedValue()` instantiates the field type twice.**
Current code calls `$fieldType->instance()` once for `storageColumn()` and a
second time (via `$this->field->fieldType->instance()`) to call `value()`. This
is inefficient but functionally correct — `FileUpload::value()` will be called
as expected. This method is existing core code and is not modified by this plan.

**2. `Admin\Media\Media` controller stubs are wired to non-existent request classes.**
The controller imports `DeleteMediaRequest` and `EditMediaRequest` which do not
exist. The `show()`, `edit()`, `update()`, and `destroy()` methods are empty.
`download()` returns the model object instead of a file response. Step 18b fills
in these stubs and creates the missing request classes.

**3. The Spatie tags migration must be deleted.**
`2025_12_27_152812_create_tag_tables.php` was left behind when `spatie/laravel-tags`
was removed from `composer.json`. On a fresh install it will try to run and fail
because the `Spatie\Tags\*` classes no longer exist in vendor. Delete this file
before running migrations.

**4. `UploadMediaRequest` and `HasMediaItems::validateUpload()` both check
mime type and file size.** The request validates at the HTTP boundary; the
trait validates programmatically. This is intentional layering, not duplication
— leave both in place.

---

## Do not start two steps in parallel.

---

## Step 1 — Delete the Spatie Tags Migration

```bash
rm database/migrations/2025_12_27_152812_create_tag_tables.php
```

Confirm no other file in the project references `Spatie\Tags`:

```bash
grep -r "Spatie\\\\Tags\|HasTags\|laravel-tags" app/ config/ database/
```

Any results must be removed before proceeding.

---

## Step 2 — Rewrite `create_media_table.php`

Replace the entire file contents. The `library_id` column is added as a plain
nullable integer here — its FK constraint is deferred to Step 5 because
`media_libraries` does not exist at this timestamp.

**File:** `database/migrations/2025_12_26_134324_create_media_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid()->nullable()->unique();

            // FK added in a later migration once media_libraries exists.
            $table->unsignedBigInteger('library_id')->nullable()->index();

            $table->string('name');
            $table->string('file_name');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size');

            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
```

---

## Step 3 — Rewrite `create_media_library_table.php`

Replace the entire file contents. The `field_layout_id` column is added as a
plain nullable integer — its FK constraint is deferred to Step 5 because
`field_layouts` does not exist until April 2026.

**File:** `database/migrations/2025_12_27_160903_create_media_library_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('handle')->index();

            // FK added in a later migration once field_layouts exists.
            $table->unsignedBigInteger('field_layout_id')->nullable()->index();

            $table->string('adapter', 50)->default('local');
            $table->json('adapter_settings')->nullable();
            $table->json('allowed_types')->nullable();
            $table->unsignedInteger('max_size')->default(10);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->unique(['name', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_libraries');
    }
};
```

---

## Step 4 — Create New Table Migrations

Check the current last migration timestamp in `database/migrations/` before
creating these files, and use timestamps that come after it. They must run
after `fields`, `categories`, and all other core tables exist. The examples
below use `2026_04_28_000002` onward — adjust if newer migrations have been
added to the repo.

### 4a — `mediables`

**File:** `database/migrations/2026_04_28_000002_create_mediables_table.php`

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

            // Sentinel: 0 = direct attachment (avatar, library browser pick).
            //           N = attached through a specific FileUpload field.
            // NOT nullable — most SQL engines permit multiple NULLs in a unique
            // index, so a nullable field_id would not protect against duplicate
            // direct attachments at the DB level. The sentinel keeps the column
            // non-null so the unique constraint works for all rows.
            // No FK constraint on field_id because 0 is not a valid fields.id.
            $table->unsignedBigInteger('field_id')->default(0)->index();

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

> `field_id = 0` → direct attachment (avatar, library browser pick).
> `field_id = N` → attached through a specific FileUpload field on that model.

### 4b — `media_transformations`

**File:** `database/migrations/2026_04_28_000003_create_media_transformations_table.php`

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

### 4c — Deferred FK constraint (`media_libraries.field_layout_id` only)

**File:** `database/migrations/2026_04_28_000004_add_media_foreign_keys.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // media_libraries.field_layout_id → field_layouts
        // Cannot be in create_media_library_table because field_layouts does
        // not exist until April 2026.
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->foreign('field_layout_id')
                  ->references('id')
                  ->on('field_layouts')
                  ->nullOnDelete();
        });

        // NOTE: media.library_id intentionally has NO FK constraint.
        //
        // The library deletion flow is:
        //   1. Library record is deleted.
        //   2. ProcessMediaLibraryRemoval job soft-deletes media by library_id.
        //   3. PurgeDeletedMedia job removes physical files after the grace period.
        //
        // A nullOnDelete() FK would null out library_id rows the moment the
        // library is deleted, causing step 2 to find nothing and leaving media
        // records permanently orphaned. A cascadeOnDelete() would hard-delete
        // media records immediately, bypassing the grace period entirely.
        // Leaving library_id as a plain indexed column is the correct choice
        // for this async cleanup pattern.
    }

    public function down(): void
    {
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->dropForeign(['field_layout_id']);
        });
    }
};
```

**Run migrations:**

```bash
php artisan migrate
```

---

## Step 5 — Rewrite `App\Models\Media`

Remove the dead Spatie import and mis-named `media_library()` relation.
Retain the existing `categories()` MorphToMany — it is used by the upload
action and is part of the model's current contract.

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

    /** Preserved from the original model — used by UploadMedia action. */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
                    ->withTimestamps();
    }

    // ── Field-scoped usage queries ─────────────────────────────────────────

    /**
     * Raw pivot rows for all field-driven references to this media item.
     * Columns returned: mediable_type, mediable_id, field_id,
     *                   field_name, field_handle, sort_order.
     *
     * Eloquent's morphedByMany cannot handle a pivot that references multiple
     * distinct model types cleanly, so the raw query builder is used instead.
     */
    public function fieldUsages(): \Illuminate\Database\Query\Builder
    {
        // field_id = 0 is the sentinel for direct attachments; anything > 0 is
        // a field-driven reference. The column is NOT NULL so whereNotNull would
        // return direct attachments too — use where('field_id', '>', 0) instead.
        return DB::table('mediables')
            ->where('media_id', $this->id)
            ->where('field_id', '>', 0)
            ->join('fields', 'fields.id', '=', 'mediables.field_id')
            ->select(
                'mediables.*',
                'fields.name as field_name',
                'fields.handle as field_handle'
            );
    }

    public function isReferencedByField(): bool
    {
        return DB::table('mediables')
            ->where('media_id', $this->id)
            ->where('field_id', '>', 0)
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

## Step 6 — Update `App\Models\Media\Library` and Its Callers

### 6a — Rewrite the model

Apply existing traits (`HasCategoryGroups`, `HasFieldGroups`, `HasFieldLayout`)
and the new `HasMediaItems` trait (created in Step 8). Add `field_layout_id`
to `$fillable`. No snake_case aliases — callers are updated in Step 6b.

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

### 6b — Update callers to camelCase

Three files call the now-removed snake_case relation methods. Update them
before running the application. While here, the detach/attach loops in
`EditMediaLibrary` are replaced with `sync()` — the correct pattern used
everywhere else in the codebase.

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

Two calls to `with('category_groups')` — one in `create()`, one in `edit()`:

```php
// Before (both occurrences)
LibraryModel::with('category_groups')->find($id);

// After
LibraryModel::with('categoryGroups')->find($id);
```

> The local PHP variables (`$category_groups`, `$field_groups`) and the view
> data array keys passed to Blade are unaffected — they are not Eloquent
> relation names.

**Also update any existing tests** that reference `category_groups` or
`field_groups` as Eloquent relation names on `Library` (e.g. `with('category_groups')`,
`$library->category_groups`). These will fail with "Call to undefined
relationship" after Step 6a removes the inline methods. Update them to
`categoryGroups` in the same commit.

---

## Step 7 — Create `App\Models\Media\Transformation`

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

## Step 8 — Create `App\Traits\HasMediaItems`

Lives on `Media\Library`. Physical file removal only ever happens in
`purgeMedia()` — never on soft-delete.

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

        $disk     = $this->adapter;
        $folder   = $this->handle;
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Store the physical file BEFORE opening the transaction.
        // If the DB insert fails after a successful storeAs(), we catch the
        // exception below and delete the orphaned file. Storing inside the
        // transaction risks leaving a file on disk with no matching DB record
        // if the transaction rolls back after the write completes.
        $path = $file->storeAs($folder, $fileName, $disk);

        try {
            return DB::transaction(function () use ($file, $disk, $fileName, $path, $attributes) {
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
        } catch (\Throwable $e) {
            // Compensate: remove the physical file so it doesn't become orphaned.
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    /**
     * Soft-delete a media record. Physical file is NOT removed here.
     * PurgeDeletedMedia handles physical cleanup after the grace period.
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
     * Returns human-readable validation errors. Empty array = valid.
     * UploadMediaRequest also validates at the HTTP boundary; this provides a
     * second check for programmatic uploads that bypass the request layer.
     */
    public function validateUpload(UploadedFile $file): array
    {
        $errors = [];

        if ($this->max_size && $file->getSize() > ($this->max_size * 1024 * 1024)) {
            $errors[] = "File exceeds the maximum allowed size of {$this->max_size} MB.";
        }

        if (!empty($this->allowed_types)
            && !in_array($file->getMimeType(), $this->allowed_types, true)) {
            $errors[] = "File type '{$file->getMimeType()}' is not allowed in this library.";
        }

        return $errors;
    }
}
```

---

## Step 9 — Create `App\Traits\HasTransformations`

Lives on the `Media` model. Decoupled from any concrete image library.

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

    /** Returns the transformation only if it completed successfully. */
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
    // Sentinel value for field_id. 0 = direct attachment (avatar, browser pick).
    // N = attached through a specific FileUpload field.
    // NULL is intentionally avoided — most SQL engines permit multiple NULLs
    // in a unique index, so the DB-level uniqueness guarantee would not hold
    // for direct attachments. See the mediables migration for details.
    private const DIRECT_ATTACHMENT = 0;

    /** All media attached to this model via any method. */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
                    ->withTimestamps()
                    ->withPivot('sort_order', 'field_id')
                    ->orderByPivot('sort_order');
    }

    /** Media attached directly (field_id = 0). */
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
            $media->id => ['sort_order' => $sortOrder, 'field_id' => self::DIRECT_ATTACHMENT],
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

    /** First directly-attached item, optionally scoped to a library handle. */
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

## Step 11 — Create Transformation Driver Interface and NullDriver

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

To adopt a real image library: implement the interface in one new class and
swap the binding in `AppServiceProvider::register()`. Nothing else changes.

---

## Step 12 — Create `App\Services\MediaStorageService`

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

    /** Soft-delete — file preserved until the purge job runs. */
    public function delete(Media $media): void
    {
        $media->library->removeMedia($media);
    }

    /** Hard-delete record and physical file immediately. */
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

In `register()`, add:

```php
$this->app->bind(
    \App\Services\Media\TransformationDriverInterface::class,
    \App\Services\Media\NullTransformationDriver::class
);

$this->app->singleton('media-service', fn() => new \App\Services\MediaStorageService());
```

In `boot()`, add the observer registration (morph map entries `'media'` and
`'media_library'` are already correct — leave them untouched):

```php
\App\Models\FieldValue::observe(\App\Observers\FieldValueObserver::class);
```

---

## Step 14 — Create `App\Field\Types\FileUpload`

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

        if (empty($ids)) {
            return true;
        }

        // Verify all submitted IDs actually exist (prevents orphan references).
        $found = \App\Models\Media::whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, $found);
        if (!empty($missing)) {
            return 'One or more selected files no longer exist.';
        }

        // If the field is scoped to a library, verify every item belongs to it.
        // Accept either library_id (int) or library_handle (string).
        $libraryId = $this->getSetting('library_id');
        if (!$libraryId && $handle = $this->getSetting('library_handle')) {
            $libraryId = once(fn() =>
                \App\Models\Media\Library::where('handle', $handle)->value('id')
            );
        }

        if ($libraryId) {
            $outsideLibrary = \App\Models\Media::whereIn('id', $ids)
                ->where('library_id', '!=', (int) $libraryId)
                ->pluck('id')
                ->all();
            if (!empty($outsideLibrary)) {
                return 'One or more selected files do not belong to the expected library.';
            }
        }

        // Field-level MIME type restriction (overrides the library setting).
        $allowedTypes = $this->getSetting('allowed_types');
        if (!empty($allowedTypes)) {
            $badType = \App\Models\Media::whereIn('id', $ids)
                ->whereNotIn('mime_type', (array) $allowedTypes)
                ->exists();
            if ($badType) {
                return 'One or more selected files have a disallowed file type.';
            }
        }

        return true;
    }

    /** Cast raw stored value to a plain array of integer IDs. */
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
     * FieldValue::resolvedValue() calls this so $entry->field('gallery')
     * returns Collection<Media> rather than raw IDs.
     *
     * Note: FieldValue casts value_json to array, so $raw arrives here as a
     * PHP array (already decoded), not a JSON string.
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
| `library_id` | int\|null | null | Restrict picker to a specific library |
| `library_handle` | string\|null | null | Portable alternative to `library_id` |
| `min` | int | 0 | Minimum files required |
| `max` | int\|null | null | Maximum files (null = unlimited; 1 = single-file mode) |
| `allowed_types` | array\|null | null | Override library MIME types at field level |
| `show_preview` | bool | true | Render inline file preview in the UI |

---

## Step 15 — Create `App\Observers\FieldValueObserver`

Keeps the `mediables` pivot truthful for FileUpload fields. Nothing else
should write field-scoped rows to that pivot.

`Field::$with = ['fieldType']` is already set, and `FieldValue::$with =
['field']` chains through it, so `$fieldValue->field->fieldType` is always
loaded — no N+1 in `isFileUpload()`.

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

        DB::table('mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id',   $id)
            ->where('field_id',      $fieldId)
            ->whereNotIn('media_id', $newIds ?: [0])
            ->delete();

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

## Step 16 — Create `App\Jobs\PurgeDeletedMedia` and Schedule It

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

In `routes/console.php`, add alongside the existing schedule:

```php
use App\Jobs\PurgeDeletedMedia;

Schedule::job(new PurgeDeletedMedia(graceDays: 30))->daily();
```

---

## Step 17 — Fix Broken Actions and Form Request

These files call APIs that no longer exist or have acknowledged bugs. They are
being fixed, not restructured.

### 17a — `UploadMedia` action

The current implementation calls `$library->addMedia()->toMediaCollection()`,
which is Spatie's API. The package is gone; this fatals on every upload.

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

### 17b — `DeleteMediaLibrary` action

The current implementation never dispatches `ProcessMediaLibraryRemoval`
despite the `@todo` saying it should.

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

### 17c — `ProcessMediaLibraryRemoval` job

The current `handle()` is empty. The job constructor also needs to accept the
library ID, which the action now passes.

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
        // Soft-delete all media belonging to this library.
        // Physical file removal is handled by PurgeDeletedMedia on its next run.
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

### 17d — `StoreMediaLibraryFormRequest`

`rules()` validates key `'storage'`; the DB column and all other code uses
`'adapter'`. Fix the two references.

**File:** `app/Http/Requests/Media/Library/StoreMediaLibraryFormRequest.php`

```php
// In rules() — change:
'storage' => ['required', 'string'],
// To:
'adapter' => ['required', 'string'],

// In the conditional block — change:
if ($this->data('storage') == 'local') {
// To:
if ($this->data('adapter') == 'local') {
```

---

## Step 18 — Create `App\Actions\Media\DeleteMedia`

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

## Step 18b — Fill In `Admin\Media\Media` Controller Stubs

The controller already exists but has four empty action methods, a broken
`download()`, and imports two request classes that do not yet exist. Create
the missing request classes first, then fill in the methods.

### Request classes

**File:** `app/Http/Requests/Media/EditMediaRequest.php`

```php
<?php

namespace App\Http\Requests\Media;

use App\Http\Requests\FormRequest;

class EditMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

**File:** `app/Http/Requests/Media/DeleteMediaRequest.php`

```php
<?php

namespace App\Http\Requests\Media;

use App\Http\Requests\FormRequest;

class DeleteMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [];   // no body expected; authorization is handled by middleware
    }
}
```

### Controller methods

**File:** `app/Http/Controllers/Admin/Media/Media.php` — fill in the stubs:

```php
use App\Actions\Media\DeleteMedia as DeleteMediaAction;
use App\Http\Requests\Media\DeleteMediaRequest;
use App\Http\Requests\Media\EditMediaRequest;
use App\Models\Media as MediaModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

public function show(MediaModel $media): \Illuminate\View\View
{
    return view('admin.media.show', compact('media'));
}

public function edit(MediaModel $media): \Illuminate\View\View
{
    return view('admin.media.edit', compact('media'));
}

public function update(EditMediaRequest $request, MediaModel $media): RedirectResponse
{
    $media->update($request->validated());
    return redirect()->route('admin.media.show', $media)
        ->with('success', 'Media updated.');
}

public function destroy(DeleteMediaRequest $request, MediaModel $media): RedirectResponse
{
    (new DeleteMediaAction)->delete($media);
    return redirect()->route('admin.media.index')
        ->with('success', 'Media deleted.');
}

public function download(MediaModel $media): Response
{
    if (!Storage::disk($media->disk)->exists($media->path)) {
        abort(404, 'File not found on disk.');
    }

    return Storage::disk($media->disk)->download($media->path, $media->original_name);
}
```

> The Blade views (`admin.media.show`, `admin.media.edit`) are scaffolded as
> needed. Their content is out of scope for this plan.

---

## Step 19 — Update `App\Models\User`

Add `HasMedia` and update `avatar()` to check the library before falling back
to the generated image. `laravolt/avatar` is already in `composer.json`.

```php
use App\Models\Media;
use App\Traits\HasMedia;

// Add HasMedia to the existing trait use statement
use HasFactory, Notifiable, HasApiTokens, HasRoles, Fieldable, TwoFactorAuthenticatable, HasMedia;

public function avatar(): string
{
    $media = $this->firstMedia('avatars');

    if ($media) {
        return $media->url();
    }

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

## Step 20 — Seeders

### 20a — FieldTypeSeeder

Add to the `$types` array in `database/seeders/FieldTypeSeeder.php`:

```php
['name' => 'File Upload', 'object' => \App\Field\Types\FileUpload::class],
```

### 20b — Avatars Library

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

## Step 21 — Tests

```bash
composer test
```

Minimum new coverage:

```php
// Upload creates a Media record and stores the physical file
Storage::fake('local');
$library = MediaLibrary::factory()->create(['adapter' => 'local']);
$file    = UploadedFile::fake()->image('photo.jpg');
$media   = $library->addMediaFromUpload($file);
expect($media->file_name)->toEndWith('.jpg');
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

// Observer syncs the mediables pivot on save
expect(DB::table('mediables')->where('field_id', $field->id)->count())->toBe(3);

// Library deletion dispatches the removal job
Queue::fake();
(new DeleteMediaLibrary)->delete($library);
Queue::assertPushed(ProcessMediaLibraryRemoval::class);
```

---

## Completion Checklist

- [ ] **Step 1** — `2025_12_27_152812_create_tag_tables.php` deleted; no `Spatie\Tags` references remain in app or config
- [ ] **Step 2** — `create_media_table.php` rewritten: native columns present; no Spatie columns; `library_id` is plain `unsignedBigInteger` with no FK (intentional — async cleanup pattern; see Step 4c comment)
- [ ] **Step 3** — `create_media_library_table.php` rewritten: `field_layout_id` is plain `unsignedBigInteger` (no FK yet); no Spatie columns
- [ ] **Step 4** — `mediables`, `media_transformations`, and `add_media_foreign_keys` migrations created after the last existing timestamp; `php artisan migrate` passes cleanly on a fresh database
- [ ] **Step 4a** — `mediables.field_id` is `unsignedBigInteger NOT NULL DEFAULT 0` (sentinel, not nullable); unique constraint covers all four columns; no FK on `field_id` (0 is not a valid `fields.id`)
- [ ] **Step 4c** — only `media_libraries.field_layout_id` FK added; `media.library_id` intentionally has no FK (nullOnDelete would race the cleanup job; cascadeOnDelete would skip the grace period)
- [ ] **Step 5** — `Media` model: no Spatie import; `library()` BelongsTo present; `categories()` preserved; `fieldUsages()` and `isReferencedByField()` use `where('field_id', '>', 0)` (not whereNotNull); storage helpers added
- [ ] **Step 6a** — `Library` model uses `HasCategoryGroups`, `HasFieldGroups`, `HasFieldLayout`, `HasMediaItems`; no snake_case aliases; `field_layout_id` in `$fillable`
- [ ] **Step 6b** — `CreateNewMediaLibrary` uses `categoryGroups()->sync()`; `EditMediaLibrary` uses `categoryGroups()->sync()` and `fieldGroups()->sync()` (no more detach/attach loops); `Admin\Media\Library` controller eager-loads `'categoryGroups'`; existing tests updated to camelCase relation names
- [ ] **Step 7** — `Transformation` model exists; `markComplete()` accepts nullable `$width`/`$height`
- [ ] **Step 8** — `HasMediaItems` trait created; file stored before transaction; orphan compensated on failure; sort order uses `lockForUpdate()`
- [ ] **Step 9** — `HasTransformations` trait created
- [ ] **Step 10** — `HasMedia` trait created; `DIRECT_ATTACHMENT = 0` sentinel used in `directMedia()` and `attachMedia()`; `mediaForField()` uses `once()` for string handle lookup
- [ ] **Step 11** — `TransformationDriverInterface` and `NullTransformationDriver` in `app/Services/Media/`
- [ ] **Step 12** — `MediaStorageService` in `app/Services/`
- [ ] **Step 13** — `AppServiceProvider::register()` has driver binding and `media-service` singleton; `boot()` registers `FieldValueObserver`; morph map entries unchanged
- [ ] **Step 14** — `FileUpload` field type created in `app/Field/Types/`; `value()` returns `Collection<Media>`; `validate()` checks min/max counts, ID existence in `media` table, library membership, and field-level `allowed_types`
- [ ] **Step 15** — `FieldValueObserver` created in `app/Observers/`; `deleted()` handler queries by actual `field_id` (always > 0, never 0)
- [ ] **Step 16** — `PurgeDeletedMedia` job created; scheduled daily in `routes/console.php`
- [ ] **Step 17a** — `UploadMedia` uses `media-service`; no Spatie calls
- [ ] **Step 17b** — `DeleteMediaLibrary` dispatches `ProcessMediaLibraryRemoval`
- [ ] **Step 17c** — `ProcessMediaLibraryRemoval` takes `int $libraryId`; `handle()` soft-deletes in chunks
- [ ] **Step 17d** — `StoreMediaLibraryFormRequest` validates `'adapter'` throughout
- [ ] **Step 18** — `DeleteMedia` action created
- [ ] **Step 18b** — `EditMediaRequest` and `DeleteMediaRequest` created; `show()`, `edit()`, `update()`, `destroy()`, `download()` filled in on `Admin\Media\Media` controller
- [ ] **Step 19** — `User` has `HasMedia`; `avatar()` checks `firstMedia('avatars')` before Laravolt fallback; `setAvatar()` detaches old avatars before attaching new one
- [ ] **Step 20** — `FileUpload` row in `FieldTypeSeeder`; avatars library seeded
- [ ] **Step 21** — `composer test` passes; upload, purge, field-resolution, observer, and library-deletion tests added
