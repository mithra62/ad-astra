# Media Status Layer — Implementation Plan

_Generated 2026-05-12. Verified against current codebase._

---

## Context

Entries already carry a status layer: each `EntryGroup` owns a `StatusGroup` (the palette of available statuses), and each `Entry` stores `status_id`, `status_handle`, and `status_is_public` (the last two denormalized for index-backed filtering without joins). This plan replicates that exact pattern for media.

Three models in the codebase can hold media through different mechanisms:

| Model | How it holds media | Relevant to this plan |
|---|---|---|
| `Entry` | `HasMedia` pivot + `FileUpload` field values | Yes — FileUpload must be status-aware |
| `User` | `HasMedia` pivot (`directMedia()` for avatars) | Partial — avatar path unaffected; field-driven media follows FileUpload path |
| `Category` | `Fieldable` only (no `HasMedia`) | Yes — Category can carry FileUpload fields |
| `Media\Library` | Owns `Media` records | Yes — Library governs available statuses |

---

## Architecture Decision

Status group ownership follows the `EntryGroup` model exactly:

- `Media\Library` gets a `status_group_id` FK — it declares which statuses are valid for media items it owns.
- Each `Media` record gets `status_id`, `status_handle`, `status_is_public`.
- Libraries without a `status_group_id` are ungoverned: their media has no status, and validation passes through without restriction (matches existing `nullable` convention throughout the codebase).

---

## Part 1 — Extract `HasStatusGroup` Trait

### Why a trait

The codebase already uses thin, single-purpose traits for shared relationship behaviour: `HasFieldLayout`, `HasCategoryGroups`, `HasFieldGroups`. Both `EntryGroup` and `Media\Library` will share identical implementations of `statusGroup()`, `statuses()`, and `defaultStatus()`. This belongs in a trait.

**Verified existing inline code to move:** `EntryGroup::statusGroup()` (line 30–33) and `EntryGroup::statuses()` (line 35–38) in `app/Models/EntryGroup.php` are identical to what `Library` will need. Both move into the trait.

### Step 1.1 — Create `app/Traits/HasStatusGroup.php`

```php
<?php

namespace App\Traits;

use App\Models\Status;
use App\Models\StatusGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

trait HasStatusGroup
{
    public function statusGroup(): BelongsTo
    {
        return $this->belongsTo(StatusGroup::class);
    }

    public function statuses(): HasManyThrough
    {
        return $this->hasManyThrough(
            Status::class, StatusGroup::class,
            'id', 'status_group_id', 'status_group_id', 'id'
        );
    }

    public function defaultStatus(): ?Status
    {
        return $this->statusGroup?->defaultStatus;
    }
}
```

`defaultStatus()` delegates to `StatusGroup::defaultStatus()`, which is already a `HasOne` relationship on that model (line 29–32 of `StatusGroup.php`). It does not re-implement the query.

The `hasManyThrough` argument order is copied verbatim from `EntryGroup::statuses()` to guarantee consistency.

### Step 1.2 — Update `app/Models/EntryGroup.php`

Remove the inline `statusGroup()` and `statuses()` methods and add `HasStatusGroup` to the `use` list.

```php
use App\Traits\HasCategoryGroups;
use App\Traits\HasFactory;
use App\Traits\HasFieldGroups;
use App\Traits\HasFieldLayout;
use App\Traits\HasStatusGroup;          // add

class EntryGroup extends Model
{
    use HasCategoryGroups, HasFactory, HasFieldGroups, HasFieldLayout, HasStatusGroup;
    // remove the two inline methods
```

The `fillable` array already contains `status_group_id` — nothing else changes on this model.

### Step 1.3 — Update `app/Models/Media/Library.php`

Add `HasStatusGroup` to traits, add `status_group_id` to fillable.

```php
use App\Traits\HasCategoryGroups;
use App\Traits\HasFieldGroups;
use App\Traits\HasFieldLayout;
use App\Traits\HasMediaItems;
use App\Traits\HasStatusGroup;          // add

class Library extends Model
{
    use HasFactory, HasCategoryGroups, HasFieldGroups, HasFieldLayout, HasMediaItems, HasStatusGroup;

    protected $fillable = [
        'field_layout_id', 'status_group_id', 'name', 'handle', 'adapter',
        'adapter_settings', 'allowed_types', 'max_size', 'sort_order',
    ];
```

### Step 1.4 — Update `app/Models/StatusGroup.php`

Add the inverse `mediaLibraries()` relationship so `StatusGroup` knows about both of its consumers. Currently only `entryGroups()` exists (line 25–28).

```php
use App\Models\Media\Library as MediaLibrary;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function mediaLibraries(): HasMany
{
    return $this->hasMany(MediaLibrary::class);
}
```

---

## Part 2 — Database Migrations

Run in this order. The library migration must precede the media migration because the media FK references `statuses`, but both new columns are nullable so no backfill is needed.

### Step 2.1 — `database/migrations/2026_05_12_000001_add_status_group_to_media_libraries_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->foreignId('status_group_id')
                ->nullable()
                ->after('field_layout_id')
                ->constrained('status_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->dropForeign(['status_group_id']);
            $table->dropColumn('status_group_id');
        });
    }
};
```

### Step 2.2 — `database/migrations/2026_05_12_000002_add_status_to_media_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('status_id')
                ->nullable()
                ->after('library_id')
                ->constrained('statuses')
                ->nullOnDelete();

            $table->string('status_handle')->nullable()->after('status_id')->index();
            $table->boolean('status_is_public')->default(false)->after('status_handle')->index();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropIndex(['status_handle']);
            $table->dropIndex(['status_is_public']);
            $table->dropColumn(['status_id', 'status_handle', 'status_is_public']);
        });
    }
};
```

After adding both migrations:

```bash
php artisan migrate
php artisan migrate --env=testing
```

---

## Part 3 — Model Changes

### Step 3.1 — Update `app/Models/Media.php`

Add status columns to `$fillable` and `$casts`, add the `status()` relationship, add the two query scopes.

```php
use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;
```

```php
protected $fillable = [
    'library_id', 'status_id', 'status_handle', 'status_is_public',
    'name', 'file_name', 'original_name',
    'mime_type', 'disk', 'path', 'size', 'sort_order',
];

protected $casts = [
    'size'             => 'integer',
    'sort_order'       => 'integer',
    'status_is_public' => 'boolean',
];

public function status(): BelongsTo
{
    return $this->belongsTo(Status::class);
}

public function scopePublished(Builder $query): Builder
{
    return $query->where('status_is_public', true);
}

public function scopeWithStatus(Builder $query, string $handle): Builder
{
    return $query->where('status_handle', $handle);
}
```

**Note:** `scopePublished` on `Media` checks only `status_is_public`. It does NOT include `published_at` checks — `media` has no `published_at` column and the concepts are different. Entry's `scopePublished` (lines 112–117 of `Entry.php`) checks three conditions; Media's checks one. This asymmetry is intentional.

---

## Part 4 — Repository

### Step 4.1 — Update `app/Repositories/MediaRepository.php`

`applyData` already routes `name`, `sort_order`, `fields`, and `categories` from the payload. Add `status` routing through a private helper.

The helper queries `Status` directly by `status_group_id` and `handle`. This is the same shape as the validation rule (see Part 5), so by the time the repository runs, the handle is guaranteed valid. The direct query avoids loading the relation chain through the model.

```php
use App\Models\Status;
```

Update `applyCoreAttributes`:

```php
private function applyCoreAttributes(Media $media, array $data): void
{
    if (isset($data['name'])) {
        $media->name = $data['name'];
    }

    if (array_key_exists('sort_order', $data)) {
        $media->sort_order = (int) $data['sort_order'];
    }

    if (isset($data['status'])) {
        $this->applyStatus($media, $data['status']);
    }
}

private function applyStatus(Media $media, string $handle): void
{
    $groupId = $media->library?->status_group_id;
    if (!$groupId) {
        return;
    }

    $status = Status::where('status_group_id', $groupId)
        ->where('handle', $handle)
        ->first();

    if (!$status) {
        return;
    }

    $media->status_id        = $status->id;
    $media->status_handle    = $status->handle;
    $media->status_is_public = $status->is_public;
}
```

The guard at the top of `applyStatus` handles libraries with no status group. The guard at the bottom (`if (!$status)`) is a safety net only — validation catches invalid handles before the repository runs.

---

## Part 5 — HTTP Validation

### Step 5.1 — Update `app/Http/Requests/Media/EditMediaRequest.php`

The library is already resolved via `resolvedSchema()`. Use it to constrain the status rule exactly as `StoreEntryRequest` (lines 31–38) and `EditEntryRequest` (lines 31–38) do for entries.

```php
use Illuminate\Validation\Rule;
```

```php
public function rules(): array
{
    $schema    = $this->resolvedSchema();
    $groupId   = $schema?->status_group_id;

    $statusRule = $groupId
        ? Rule::exists('statuses', 'handle')->where('status_group_id', $groupId)
        : 'string';

    return array_merge(
        [
            'name'   => ['required', 'sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'max:100', $statusRule],
        ],
        $this->schemaFieldRules($schema)
    );
}
```

When the library has no status group, `$statusRule` is plain `'string'` — any value passes but `nullable` is still in the array so null is also valid. This matches the ungoverned-library design decision.

---

## Part 6 — Upload Auto-Assignment

### Step 6.1 — Update `app/Traits/HasMediaItems.php`

When a file is uploaded, auto-assign the library's default status so every new `Media` record has a status from day one. This mirrors how `EntryService::create` assigns the default status when creating entries.

`defaultStatus()` is already available via the `HasStatusGroup` trait on `Library`. Resolve it outside the transaction (no write needed). Merge the status attributes before `$attributes` so a caller can override the status by passing `status_id` etc. in the attributes array.

```php
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

    if ($path === false) {
        throw new \RuntimeException("Failed to store uploaded file on disk '{$disk}'.");
    }

    $defaultStatus = $this->defaultStatus();

    try {
        return DB::transaction(function () use ($file, $disk, $fileName, $path, $attributes, $defaultStatus) {
            $nextOrder = (int) $this->media()->lockForUpdate()->max('sort_order') + 1;

            $statusAttributes = $defaultStatus ? [
                'status_id'        => $defaultStatus->id,
                'status_handle'    => $defaultStatus->handle,
                'status_is_public' => $defaultStatus->is_public,
            ] : [];

            return $this->media()->create(array_merge([
                'name'          => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name'     => $fileName,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'disk'          => $disk,
                'path'          => $path,
                'size'          => $file->getSize(),
                'sort_order'    => $nextOrder,
            ], $statusAttributes, $attributes));
        });
    } catch (\Throwable $e) {
        Storage::disk($disk)->delete($path);
        throw $e;
    }
}
```

---

## Part 7 — Controller Eager Load

### Step 7.1 — Update `app/Http/Controllers/Admin/Media.php`

The `update()` method already loads `library.fieldLayout.tabs.elements.field.fieldType`. Add `library.statusGroup` so the repository's `applyStatus` call has `status_group_id` available without an extra query.

```php
public function update(EditMediaRequest $request, string $id): RedirectResponse
{
    $media = MediaModel::with([
        'library.fieldLayout.tabs.elements.field.fieldType',
        'library.statusGroup',
    ])->findOrFail($id);

    $editor = app(EditMediaAction::class);
    $media  = $editor->edit($media, $request->validated());

    return redirect()->route('media.show', $media->id)
        ->with('success', trans('media.updated'));
}
```

---

## Part 8 — FileUpload Field Type

`FileUpload` (`app/Field/Types/FileUpload.php`) is used on `Entry`, `User`, and `Category` models — any model with `Fieldable`. It has two relevant methods:

### `validate()` — form submission checks

Currently validates: ID existence, library membership, MIME type (lines 38–93). Status is not checked. This is intentional for now — all FileUpload usage in the current codebase is admin-side, where an admin should be able to attach media of any status. If public-facing file submission is added in the future, a status check should be added here.

**No change needed now. Document the gap.**

### `value()` — field resolution (the important one)

Called whenever `$entry->field('gallery')` (or equivalent on any Fieldable model) resolves the stored IDs to a Collection of Media models (lines 117–128):

```php
public function value(mixed $raw): Collection
{
    $ids = $this->cast($raw);
    if (empty($ids)) {
        return collect();
    }
    return Media::whereIn('id', $ids)
        ->with('fieldValues.field.fieldType')
        ->get()
        ->sortBy(fn($m) => array_search($m->id, $ids))
        ->values();
}
```

This method has no context awareness — it returns all matched media regardless of status. This is correct for admin screens. For public templates, the caller receives a `Collection<Media>` where each item now has a `status_is_public` boolean, so a template can filter if needed:

```twig
{% for file in entry.field('gallery')|filter(m => m.status_is_public) %}
```

**No change to `value()` itself.** Adding context-awareness here would require threading an admin/public flag through `AbstractField`, which is a larger refactor than this plan covers. The attribute being present on each model is sufficient for templates to filter.

### `validate()` — status filtering of submitted IDs

One gap worth documenting: `validate()` checks that submitted IDs exist (`Media::whereIn`), but does not verify they belong to a media item with a particular status. For public forms this could let a user submit an ID for a draft media item. Since no public form currently uses FileUpload, leave `validate()` unchanged but note this is a future hardening point.

---

## Part 9 — Factory Updates

### Step 9.1 — Update `database/factories/Media/LibraryFactory.php`

Add a `withStatusGroup()` state for tests that need a governed library.

```php
use App\Models\StatusGroup;

public function withStatusGroup(): static
{
    return $this->state(fn () => [
        'status_group_id' => StatusGroup::factory(),
    ]);
}
```

### Step 9.2 — Update `database/factories/MediaFactory.php`

Add a `withStatus()` state for tests that need media in a specific status.

```php
use App\Models\Status;
use App\Models\StatusGroup;

public function withStatus(bool $isPublic = false): static
{
    return $this->state(function () use ($isPublic) {
        $status = Status::factory()->create([
            'status_group_id' => StatusGroup::factory()->create()->id,
            'is_default'      => true,
            'is_public'       => $isPublic,
        ]);

        return [
            'status_id'        => $status->id,
            'status_handle'    => $status->handle,
            'status_is_public' => $status->is_public,
        ];
    });
}
```

---

## Part 10 — Tests

Create `tests/Feature/Admin/MediaStatusTest.php`. Pattern matches `EntryStatusValidationTest.php` — same structure, same `RefreshDatabase` + `withoutMiddleware(VerifyCsrfToken::class)`.

### Helper methods

```php
private function makeSuperAdmin(): User { /* same as MediaControllerTest */ }

private function makeLibraryWithStatuses(): array
{
    $statusGroup = StatusGroup::factory()->create();
    $draft = Status::factory()->create([
        'status_group_id' => $statusGroup->id,
        'handle'          => 'draft',
        'name'            => 'Draft',
        'is_default'      => true,
        'is_public'       => false,
    ]);
    $published = Status::factory()->create([
        'status_group_id' => $statusGroup->id,
        'handle'          => 'published',
        'name'            => 'Published',
        'is_default'      => false,
        'is_public'       => true,
    ]);
    $library = Library::create([
        'name'            => 'Photos',
        'handle'          => 'photos',
        'adapter'         => 'local',
        'status_group_id' => $statusGroup->id,
    ]);
    return [$library, $statusGroup, $draft, $published];
}

private function makeMedia(Library $library, array $overrides = []): Media
{
    return Media::factory()->create(array_merge([
        'library_id' => $library->id,
        'disk'       => 'local',
        'path'       => 'uploads/photo.jpg',
        'file_name'  => 'photo.jpg',
    ], $overrides));
}
```

### Model and relationship tests

```
test_media_belongs_to_status
  - Create Status and Media with that status_id.
  - Assert $media->status is the correct Status instance.

test_library_status_group_relationship
  - Create Library with a StatusGroup.
  - Assert $library->statusGroup->id matches.

test_library_statuses_returns_all_in_group
  - Create Library linked to a StatusGroup with two statuses.
  - Assert $library->statuses->pluck('handle') contains both handles.

test_library_default_status_returns_default
  - Create Library with a StatusGroup; one status has is_default = true.
  - Assert $library->defaultStatus()->handle equals that status's handle.

test_library_default_status_returns_null_when_no_status_group
  - Create Library with status_group_id = null.
  - Assert $library->defaultStatus() is null.

test_status_group_has_media_libraries_inverse
  - Create StatusGroup and Library pointing to it.
  - Assert $statusGroup->mediaLibraries->first()->id === $library->id.
```

### Scope tests

```
test_published_scope_returns_only_public_media
  - Create Media with status_is_public = true, another with false.
  - Assert Media::published()->count() === 1.
  - Assert the result contains only the public item.

test_with_status_scope_filters_by_handle
  - Create Media with status_handle 'draft' and another with 'published'.
  - Assert Media::withStatus('draft')->count() === 1.
```

### Upload auto-assignment tests

```
test_upload_assigns_default_status_when_library_has_status_group
  - Storage::fake('local').
  - Library linked to StatusGroup; default status is 'draft' (is_public = false).
  - Call $library->addMediaFromUpload(UploadedFile::fake()->image('x.jpg')).
  - Assert $media->status_handle === 'draft'.
  - Assert $media->status_is_public === false.
  - Assert $media->status_id === $draft->id.

test_upload_leaves_status_null_when_library_has_no_status_group
  - Storage::fake('local').
  - Library with status_group_id = null.
  - Call addMediaFromUpload.
  - Assert $media->status_id is null.
  - Assert $media->status_handle is null.

test_upload_caller_attributes_override_default_status
  - Storage::fake('local').
  - Library has default status 'draft'. Call addMediaFromUpload with
    attributes containing status_id of the 'published' Status.
  - Assert $media->status_handle === 'published'.
```

### HTTP validation tests (the most important — mirror EntryStatusValidationTest)

```
test_update_accepts_status_from_librarys_status_group
  - Make super admin, library with statuses, media in that library.
  - PUT media.update with ['name' => 'x', 'status' => 'published'].
  - assertRedirect(route('media.show', $media->id)).
  - Assert $media->fresh()->status_handle === 'published'.
  - Assert $media->fresh()->status_is_public === true.

test_update_rejects_status_from_another_status_group
  - Make super admin, library with status group A.
  - Create status group B with handle 'other-status'.
  - PUT media.update with ['status' => 'other-status'].
  - assertSessionHasErrors('status').
  - Assert $media->fresh()->status_handle unchanged.

test_update_accepts_null_status
  - Library with a status group.
  - PUT media.update with ['status' => null].
  - No validation error (nullable rule).

test_update_accepts_any_status_when_library_has_no_status_group
  - Library with status_group_id = null.
  - PUT media.update with ['status' => 'anything-at-all'].
  - No validation error (ungoverned fallback).

test_update_syncs_status_is_public_when_status_changes
  - Media starts with status_handle = 'draft', status_is_public = false.
  - PUT media.update with ['status' => 'published'] (published has is_public = true).
  - Assert $media->fresh()->status_is_public === true.
  - Assert $media->fresh()->status_handle === 'published'.
  - Assert $media->fresh()->status_id === $published->id.
```

### FileUpload field tests

Add to the relevant unit test for `FileUpload` (likely `tests/Unit/Field/Types/FileUploadTest.php` or similar):

```
test_value_returns_all_media_regardless_of_status
  - Create two Media items: one with status_is_public = true, one false.
  - Call FileUpload::value() with both IDs.
  - Assert both are returned (value() is context-agnostic).

test_value_result_exposes_status_is_public_on_each_item
  - Create Media with status_is_public = true.
  - Assert the returned item has status_is_public === true.
  - (Verifies the attribute is present for template-level filtering.)

test_validate_passes_regardless_of_media_status
  - Create Media with status_is_public = false.
  - Call FileUpload::validate() with that ID.
  - Assert it returns true.
  - (Documents intentional gap: validate() does not check status.)
```

---

## Implementation Order

These steps are independent of each other unless noted. Steps 1–4 can be done together before running migrations.

| # | Step | Depends on | File |
|---|---|---|---|
| 1 | Create `HasStatusGroup` trait | — | `app/Traits/HasStatusGroup.php` |
| 2 | Update `EntryGroup` (remove inline methods, add trait) | Step 1 | `app/Models/EntryGroup.php` |
| 3 | Update `Library` model (add trait + fillable) | Step 1 | `app/Models/Media/Library.php` |
| 4 | Update `StatusGroup` (add `mediaLibraries()`) | — | `app/Models/StatusGroup.php` |
| 5 | Run migrations | Steps 2–4 | `php artisan migrate [--env=testing]` |
| 6 | Update `Media` model (fillable, casts, scopes, relationship) | Step 5 | `app/Models/Media.php` |
| 7 | Update `MediaRepository` (`applyStatus` helper) | Step 6 | `app/Repositories/MediaRepository.php` |
| 8 | Update `EditMediaRequest` (status validation rule) | Step 3 | `app/Http/Requests/Media/EditMediaRequest.php` |
| 9 | Update `HasMediaItems` (default status on upload) | Steps 3, 6 | `app/Traits/HasMediaItems.php` |
| 10 | Update admin `Media` controller (eager load) | — | `app/Http/Controllers/Admin/Media.php` |
| 11 | Update factories | Step 5 | `database/factories/Media/LibraryFactory.php`, `database/factories/MediaFactory.php` |
| 12 | Write tests | Steps 1–11 | `tests/Feature/Admin/MediaStatusTest.php` |

Steps 1, 2, 3, 4 can be done in parallel. Steps 6, 7, 8, 9, 10 can be done in parallel after Step 5. Tests should be written after all implementation is done but verified against `php artisan test` before committing.

---

## Known Gaps (out of scope for this plan)

- **`FileUpload::validate()` status check** — validate() does not verify that submitted media IDs have a public status. Harmless for now (admin-only forms), but needs attention if public-facing file submission is added.
- **Bulk status update** — no batch operation exists for updating multiple media items to a new status. The admin media index has no status column or filter UI.
- **API resource** — `MediaResource` does not exist yet. When added, it should expose `status_handle` and `status_is_public`.
- **`app:validate-class-references` command** — does not need updating; it validates PHP class names stored in the DB, not status handles.
