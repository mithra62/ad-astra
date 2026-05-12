# Media Layer — Issue Report

*Compiled 2026-05-11. Based on comparison of `media-layer-implementation.md` against the live codebase on the `media` branch.*

**Summary:** 0 critical · 7 high · 5 medium · 5 low

---

## Critical — Runtime Failures

### ~~C1. `media` table missing `alt_text` and `title` columns~~ — resolved by design decision

`alt_text` will be implemented as a custom field on the library's field layout (using the existing `Fieldable` trait on `Media`), applied only to libraries that contain images. `title` is considered canonical to `name` and will not be added as a separate column. No migration changes needed. See H8 for a follow-on concern this decision introduces.

---

### ~~C2. `Media::$fillable` missing `uuid`~~ — resolved by removal

The `uuid` column provided no value: all media routes use integer IDs, all media access is behind auth middleware, and the physical file path already uses a UUID-based filename. The column was dead infrastructure. Removed from the migration, `HasMediaItems::addMediaFromUpload()`, and `MediaFactory`.

---

### ~~C3. `MediaStorageService::delete()` and `purge()` crash when `library_id` is null~~ — fixed

`delete()` and `purge()` now operate directly on the media item without loading the library. `removeMedia()` and `purgeMedia()` removed from `HasMediaItems` (neither used `$this`). `Storage` import removed from the trait. Two new null-library tests added to `MediaStorageServiceTest`; four now-dead `HasMediaItemsTest` tests removed.

---

### ~~C4. `EditMediaRequest` crashes when `library_id` is null~~ — fixed (see also H2)

Replaced the three independent `resolvedFields()` calls with a private `resolvedSchema()` method that uses `MediaLibrary::with(...)->find()` (returns null gracefully) and caches the result on the instance. Null `$media` and null `library_id` both resolve to `null`, which `schemaFieldRules()` already handles by returning an empty array. Also adds `fieldType` to the eager-load chain, eliminating an N+1 in `schemaFieldRules()`. Covered by `EditMediaRequestTest`.

---

### ~~C5. `HasMediaItems::addMediaFromUpload()` does not check for `storeAs()` failure~~ — fixed

Added `if ($path === false)` guard between `storeAs()` and the transaction, throwing `RuntimeException` immediately with no DB write. Note: `FilesystemAdapter::put()` catches Flysystem exceptions internally and returns `false`, so this path is reachable in production. Covered by two new tests in `HasMediaItemsTest` that mock the filesystem manager at the container level to return `false` from `putFileAs()`.

---

### ~~C6. Spatie tags migration still present (converted to no-op but not deleted)~~ — resolved manually

`database/migrations/2025_12_27_152812_create_tag_tables.php` deleted.

---

## High — Incorrect Behaviour

### H1. `EditMediaRequest` diverges from the plan and the update flow
**File:** `app/Http/Requests/Media/EditMediaRequest.php`

The plan (Step 18b) specifies a simple request validating `name`, `alt_text`, and `title`. The actual request dynamically generates rules from the library's field layout. This is a legitimate design evolution, but it has two downstream problems:

- **`alt_text` and `title` are validated and handled nowhere.** They were in the plan, were never added to the migration or `$fillable`, and the new dynamic request also does not include them. If those fields are no longer part of the design, the migration plan and model need to be formally updated to reflect that.
- **`MediaRepository::applyCoreAttributes()` only handles `name` and `sort_order`** (`app/Repositories/MediaRepository.php:68-77`). Dynamic field values submitted under `fields.*` go through `applyFieldValues()` correctly, but any core scalar metadata beyond `name` cannot be updated through the current edit flow.

---

### ~~H2. `EditMediaRequest` executes 6+ redundant DB queries per request~~ — fixed with C4

Resolved as part of the C4 fix. The private `resolvedSchema()` cache means `messages()` and `attributes()` incur zero queries after `rules()` has fired. Verified by two query-count assertions in `EditMediaRequestTest`.

---

### H3. `HasMedia::mediaForField()` — `once()` memoization is a no-op
**File:** `app/Traits/HasMedia.php:56`

```php
: once(fn () => \App\Models\Field::where('handle', $field)->value('id'));
```

A new anonymous closure is created on every call to `mediaForField()`. `once()` keys its cache by closure identity (`spl_object_id`), so each call produces a unique object and `once()` always executes the query. The memoization never fires. A static property cache keyed by handle string would be needed for the intended effect.

---

### H4. `Admin\Media\Library::show()` bypasses the `media()` relation, ignoring `sort_order`
**File:** `app/Http/Controllers/Admin/Media/Library.php:67-71`

Builds a raw `where(['library_id' => $id])` query instead of `$library->media()`, which has `->orderBy('sort_order')`. Media in the library view is returned in undefined (insertion) order.

---

### H5. Route name collision between explicit routes and resource
**File:** `routes/admin.php:99-102`

```php
Route::get('media/{library_id}/create', ...)->name('media.create');
Route::post('media/{library_id}/create', ...)->name('media.store');
Route::resource('media', Media::class);   // also registers media.create and media.store
```

The resource's registration comes after the explicit routes, so `route('media.create')` resolves to the resource's `GET /admin/media/create` (no `library_id`), not the explicit route. Links generated with `route('media.create')` go to the wrong URL. The resource's `GET /admin/media/create` also hits `Admin\Media::create(string $library_id)` without the required parameter, which will error.

---

### H6. `HasTransformations::transform()` re-uses failed transformations with stale paths
**File:** `app/Traits/HasTransformations.php:36-43`

If a transformation exists with status `failed`, it is reused as-is (it is non-null and non-complete) and dispatched again. The stored `path` in the failed record may be stale or wrong if `params` changed between calls, but `transform()` does not update it before dispatching.

---

### H7. `TransformationDriverInterface` API is incoherent
**File:** `app/Services/Media/TransformationDriverInterface.php`

The interface declares fluent builder methods (`resize()`, `fit()`, `crop()`, etc.) returning `static`, implying a builder chain. But `dispatch(Transformation $t)` operates on a pre-built `Transformation` model whose `params` JSON column already holds the parameters. There is no clear path from calling `$driver->resize(100, 100)` to having those values influence `dispatch()`. Any real driver implementation would have to invent its own convention. The builder methods and the `dispatch`/`applySync` contract belong in separate interfaces, or the builder methods should be removed.

---

### H8. `FileUpload::value()` does not eager-load field values on returned Media models
**File:** `app/Field/Types/FileUpload.php:116-123`

`value()` returns `Collection<Media>` via a bare `Media::whereIn('id', $ids)->get()` with no eager loading. Because `alt_text` is now a custom field (resolved by design — see C1), any template or code that accesses `$media->field('alt_text')` on each item in the resolved collection will trigger one lazy-load query per media item. For a gallery field with N items this is N+1 queries.

`value()` should eager-load `fieldValues.field.fieldType` on the returned collection before returning it:

```php
return Media::whereIn('id', $ids)
    ->with('fieldValues.field.fieldType')
    ->get()
    ->sortBy(fn ($m) => array_search($m->id, $ids))
    ->values();
```

---

## Medium — Broken Contracts / Missing Pieces

### M1. `Library::create()` action generates a FieldLayout without uniqueness protection
**File:** `app/Actions/Media/Library/CreateNewMediaLibrary.php:13`

Always calls `FieldLayout::create(['handle' => $input['handle'] . '-layout-media'])`. If a field layout with that handle already exists (from a previously deleted library or a naming collision), the insert fails without a clear error. No uniqueness check or `firstOrCreate` guard is in place.

---

### M2. `Admin\Media\Library::edit()` eager-loads only `categoryGroups`, not `fieldGroups`
**File:** `app/Http/Controllers/Admin/Media/Library.php:102`

Passes `with('categoryGroups')` only. The edit view needs to know which field groups are currently attached in order to pre-select them. Without eager-loading, those are lazy-loaded (N+1) or missing from the view data.

---

### M3. `UploadMediaRequest` requires `name`, preventing filename fallback
**File:** `app/Http/Requests/Media/Library/UploadMediaRequest.php:33-36`

`name` is `required`. `HasMediaItems::addMediaFromUpload()` already derives a name from `PATHINFO_FILENAME` of the original filename. Making `name` required at the HTTP boundary forces every upload form to include an explicit name field even when a sensible default exists.

---

### M4. `MediaStorageService::delete()` indirection through library is unnecessary
Beyond the null-crash noted in C3, routing a simple soft-delete through `$media->library->removeMedia($media)` (which just calls `$media->delete()`) adds a needless layer and an extra DB query to load the library. `DeleteMedia` action and `MediaStorageService` should call `$media->delete()` directly.

---

### M5. Plan Step 21 test coverage gaps
The plan's minimum test list specifies observer sync, `PurgeDeletedMedia`, `HasMediaItems` upload/purge, and `FileUpload::value()` resolution. Tests for `PurgeDeletedMedia`, `DeleteMediaAction`, `MediaStorageService`, `HasMediaItems`, and `HasMedia` all exist. Missing coverage:

- `FieldValueObserver` syncing the `mediables` pivot on `saved` and `deleted`
- `FileUpload::validate()` (library membership check, MIME type restriction, min/max counts)
- `Admin\Media` controller routes and responses
- `EditMedia` / `MediaRepository` update flow

---

## Low — Plan vs. Implementation Gaps

### L1. `ProcessMediaLibraryRemoval::whereNull('deleted_at')` is redundant
**File:** `app/Jobs/ProcessMediaLibraryRemoval.php:21`

The `Media` model uses `SoftDeletes`, so the default Eloquent scope already excludes soft-deleted records. The explicit `whereNull('deleted_at')` is a no-op but harmless.

---

### L2. `media_libraries` composite unique constraint mismatches validation intent
**File:** `database/migrations/2025_12_27_160903_create_media_library_table.php`

The migration has `$table->unique(['name', 'handle'])` — only the *combination* needs to be unique at the DB level. `StoreMediaLibraryFormRequest` validates each column individually (`Rule::unique('media_libraries', 'name')` and `Rule::unique('media_libraries', 'handle')`), which is stricter. The schema does not enforce the actual intent, though it cannot corrupt data since application validation is tighter.

---

### L3. `HasMedia::syncMedia()` loses `sort_order` for all synced items
**File:** `app/Traits/HasMedia.php:84`

`$this->directMedia()->sync($mediaIds)` with a flat array inserts pivot rows relying on the `sort_order` DB default (0). All synced items end up with `sort_order = 0`. If ordering matters for direct attachments, callers must build the keyed `[$id => ['sort_order' => $n, 'field_id' => 0]]` array themselves. This is undocumented on the method.

---

### L4. `Admin\Media\Library::confirm()` passes a raw string instead of calling `trans()`
**File:** `app/Http/Controllers/Admin/Media/Library.php:138`

```php
->with('failure', 'media.library.not_found');   // raw key, not translated
```

All other flash messages in the controller call `trans('...')`. Users see the literal key string instead of a translated message.

---

### L5. `Admin\Media\Library::upload()` flash messages use raw strings
**File:** `app/Http/Controllers/Admin/Media/Library.php:154-157`

`'media.uploaded'` and `'media.upload_failed'` are passed directly without `trans()`, inconsistent with the rest of the controller and with `destroy()`/`update()` in the same class.
