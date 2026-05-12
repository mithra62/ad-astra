# Media Layer — Issue Report

*Compiled 2026-05-11. Based on comparison of `media-layer-implementation.md` against the live codebase on the `media` branch.*

**Summary:** 0 critical · 6 high · 5 medium · 5 low

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

### ~~H1. `EditMediaRequest` diverges from the plan and the update flow~~ — resolved by design decision

`alt_text` is now a custom field on the library's field layout (see C1); `title` is canonical to `name`. The dynamic `EditMediaRequest` is therefore correct. `MediaRepository::applyCoreAttributes()` handling only `name` and `sort_order` is complete — those are the only core scalar attributes remaining on the model. No code changes needed.

---

### ~~H2. `EditMediaRequest` executes 6+ redundant DB queries per request~~ — fixed with C4

Resolved as part of the C4 fix. The private `resolvedSchema()` cache means `messages()` and `attributes()` incur zero queries after `rules()` has fired. Verified by two query-count assertions in `EditMediaRequestTest`.

---

### ~~H3. `HasMedia::mediaForField()` — `once()` memoization is a no-op~~ — fixed

Replaced `once(fn () => ...)` with a private `resolveFieldHandle(string $handle): ?int` method that checks/populates `private static array $fieldHandleCache`. The static array is keyed by handle string and shared across all trait hosts and instances in a process, so the `fields` lookup fires at most once per unique handle. `setUp()` in `HasMediaTest` uses `ReflectionProperty` to reset the cache before each test. Three new caching tests added.

---

### ~~H4. `Admin\Media\Library::show()` bypasses the `media()` relation, ignoring `sort_order`~~ — fixed

Replaced the raw `MediaModel::query()->where(['library_id' => $id])->paginate(20)` with `$library->media()->paginate(20)`. The `Library::media()` `hasMany` already carries `->orderBy('sort_order')`, so ordering is now correct and the `MediaModel` import was removed as unused.

---

### ~~H5. Route name collision between explicit routes and resource~~ — fixed

Added `->except(['create', 'store'])` to the `Route::resource('media', ...)` call. Those two actions are covered by the explicit `media.create` / `media.store` routes above it (which carry the required `{library_id}`). The resource now only registers `index`, `show`, `edit`, `update`, and `destroy` — the five actions the controller implements meaningfully.

---

### ~~H6. `HasTransformations::transform()` re-uses failed transformations with stale paths~~ — fixed

`transform()` now has four explicit branches: `complete` → return as-is; `pending` → return as-is (job in flight, no re-dispatch); `failed` → reset `path`, `params`, and `status` to `pending` then dispatch; `null` → create fresh record then dispatch. Two new tests added: one verifying the failed record is updated with current params/path before dispatch, one verifying pending records are returned without creating a duplicate.

---

### ~~H7. `TransformationDriverInterface` API is incoherent~~ — fixed

Adopted the executor-only model. Removed the six fluent builder methods (`resize`, `fit`, `crop`, `quality`, `format`, `sharpen`, `watermark`) from the interface and their no-op implementations from `NullTransformationDriver`. The interface now declares only `dispatch()` and `applySync()`. Drivers read operation intent from `$transformation->params` (the JSON column), which already crosses the queue boundary correctly. No callers of the removed methods existed in the codebase.

`GDTransformationDriver` implemented as the default driver (replaces `NullTransformationDriver` in `AppServiceProvider`). Params schema (Option A — named keys): `width`, `height`, `mode` (`cover` / `contain` / `exact`, default `cover`), `format` (`jpg` / `png` / `gif` / `webp`), `quality` (0–100, default 85). `ProcessTransformation` queued job added — driver-agnostic, resolves driver from container. `HasTransformationsTest` now rebinds `NullTransformationDriver` in setUp to stay isolated from the active driver. 9 new tests in `GDTransformationDriverTest`.

---

### ~~H8. `FileUpload::value()` does not eager-load field values on returned Media models~~ — fixed

Added `->with('fieldValues.field.fieldType')` to the `whereIn` query in `value()`. Also fixed a secondary `once()` memoization bug in `validate()`: the `library_handle → id` lookup used `once(fn () => ...)` which never cached (same root cause as H3). Replaced with `private static array $libraryHandleCache` using the same static-array pattern. `setUp()` in `FileUploadTest` resets the cache via `ReflectionProperty`. Three new tests added: eager-load assertion, query-count bound, and handle-cache verification.

---

## Medium — Broken Contracts / Missing Pieces

### ~~M1. `Library::create()` action generates a FieldLayout without uniqueness protection~~ — resolved by design decision

The automatic `FieldLayout::create()` call was removed entirely. Field layouts are first-class citizens managed manually through the FieldLayout admin UI; a library is created without one and a layout is assigned explicitly if needed. The stale `use App\Models\FieldLayout` import was also removed.

---

### ~~M2. `Admin\Media\Library::edit()` eager-loads only `categoryGroups`, not `fieldGroups`~~ — fixed manually

`with('categoryGroups')` expanded to `with('categoryGroups', 'fieldGroups')` on line 97.

---

### ~~M3. `UploadMediaRequest` requires `name`, preventing filename fallback~~ — fixed manually

`name` rule changed from `required` to `nullable` — upload forms no longer need to supply an explicit name, and `addMediaFromUpload()` falls back to `PATHINFO_FILENAME` of the original filename.

---

### ~~M4. `MediaStorageService::delete()` indirection through library is unnecessary~~ — resolved with C3

`delete()` and `purge()` were rewritten to operate directly on the media item as part of the C3 fix. Stale `use App\Models\Media\Library` import removed in M4 pass.

---

### ~~M5. Plan Step 21 test coverage gaps~~ — closed

All four gaps addressed:
- `FieldValueObserver` — already covered (6 tests for `saved`/`deleted`/sort-order)
- `FileUpload::validate()` — already covered; handle-cache test added this session
- `EditMedia` / `MediaRepository` — `EditMediaActionTest` added (7 tests: name, sort_order, categories, field values, return contract)
- `Admin\Media` controller — `MediaControllerTest` (13 feature tests) and `MediaLibraryControllerTest` (16 feature tests) added

Three latent production bugs found and fixed during test authoring: missing `Library` import in `MediaStorageService`, missing `Storage` import in `HasMediaItems` catch block, and `UploadMedia` passing `null` name overriding the PATHINFO_FILENAME default.

---

## Low — Plan vs. Implementation Gaps

### ~~L1. `ProcessMediaLibraryRemoval::whereNull('deleted_at')` is redundant~~ — resolved manually

Redundant `whereNull('deleted_at')` clause removed. The `SoftDeletes` scope on `Media` already excludes soft-deleted records from the default query.

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
