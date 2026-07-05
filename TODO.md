# TODO

Findings from a read-only codebase review (2026-07-05, alpha2 branch). Ordered by priority within each section. No fixes have been applied.

## High priority

- [ ] **Wire up entry hierarchy inputs — validated but never consumed.**
  `StoreEntryRequest` / `EditEntryRequest` validate `parent_entry_id`, `uri`, `depth`, `sort_order`, `redirect_url`, `redirect_status`, but `EntryService::create()` / `syncTreeNode()` read `$data['parent_id']` — nothing maps the two names, so a parent submitted via API or admin form is silently ignored and tree nodes always land at root. `redirect_url` / `redirect_status` are fillable on `EntryTree` and honored by `EntryTreeRouteDriver`, but `createTreeNode()` / `syncTreeNode()` never persist them from request data.
  Files: `packages/core/src/Http/Requests/Entry/StoreEntryRequest.php:81`, `packages/core/src/Http/Requests/Entry/EditEntryRequest.php:75`, `packages/core/src/Services/EntryService.php:97,131,297`
  Suggested test: POST `parent_entry_id` to the entries API, assert the created tree node's `parent_id`.

- [ ] **Replace scaffold markup in the hierarchy partial.**
  `packages/core/resources/views/admin/entries/_hierarchy.twig` ships hardcoded `<option>` entry IDs (22060, 22004, 21970, 21922) and is included in the live create/edit forms (`create.twig:137`, `edit.twig:137`).

- [ ] **FileUpload: new `library` setting bypasses library validation.**
  `FileUpload::validate()` only scopes media to a library via legacy `library_id` / `library_handle` settings; the current settings form stores the new `library` array (select_multiple), which `resolveLibraryId()` prefers but `validate()` ignores — so fields configured through today's UI get no library-membership enforcement on save. Also: the UI implies multiple libraries, but `resolveLibraryId()` uses only the first.
  File: `packages/core/src/Field/Types/FileUpload.php:108-121,212-235`

## Medium priority

- [ ] **Reconcile `afterUpdate` transaction semantics with the documented pattern.**
  `EntryRepository::applyData()` comments say `afterUpdate` runs outside the transaction (side effects survive a rollback), but `EntryService::update()` wraps `applyData()` in an outer `DB::transaction`, so `afterUpdate` actually runs inside it. Create and update paths now give lifecycle hooks different guarantees — decide which semantics are intended and fix the code or the comment.
  Files: `packages/core/src/Repositories/EntryRepository.php:307-311`, `packages/core/src/Services/EntryService.php:59-75`

- [ ] **Normalize API authorization semantics.**
  In `Api/v1/Entries.php`: `index`/`show` abort **404** on missing permission, `destroy` aborts **403**, `store`/`update` delegate to FormRequest (403). Pick hide-vs-forbid once and apply consistently (check the other v1 controllers too). Align the OpenAPI response annotations with whichever choice wins.
  File: `packages/core/src/Http/Controllers/Api/v1/Entries.php:63,160,252`

- [ ] **Standardize permission naming.**
  Mixed plural/singular: `read entries` vs `create entry` / `edit entry` / `delete entry`.

- [ ] **`Entries::index` never verifies the entry group exists.**
  The documented 404 for an unknown `group_id` can't happen — an empty page is returned instead.
  File: `packages/core/src/Http/Controllers/Api/v1/Entries.php:61-82`

- [ ] **Social login: account linking by email.**
  `UserService::firstOrCreateFromSocial()` matches an existing account purely by email. If any enabled provider does not verify email ownership, this is an account-takeover vector. Worth an explicit decision/note per provider; also has a small check-then-create race (no unique-violation retry like elsewhere in the codebase).
  File: `packages/core/src/Services/UserService.php:104-130`

## Low priority / housekeeping

- [ ] **`EntryService.php` internal organization.**
  Section banner comments no longer match method placement (tree helpers under "Custom Fields (Fieldable)", CRUD under "Entry Tree", etc.). ~400 of 703 lines are tree logic — consider extracting an `EntryTreeService`.

- [ ] **Memoize schema resolution in entry form requests.**
  `EditEntryRequest` runs `findOrFail` + schema resolution separately in `rules()` and `attributes()`; `StoreEntryRequest` resolves group/type schemas up to three times on a failing request (`rules()`, `messages()`, `attributes()`).

- [ ] **Media upload filename extension comes from the client.**
  `HasMediaItems::addMediaFromUpload()` uses `getClientOriginalExtension()` for the stored filename. MIME type is checked against the library allowlist (when configured), but the extension is client-controlled — prefer `$file->extension()` (guessed from MIME), especially for libraries with an empty `allowed_types` on a publicly served disk.
  File: `packages/core/src/Traits/HasMediaItems.php:39`

- [ ] **`EntryQueryBuilder::where()` collapses an explicit `null` third argument.**
  `where('col', '=', null)` becomes a two-argument call — a null comparison can't be expressed. Same limitation applies to `whereField()`'s two-arg shorthand.
  File: `packages/core/src/Builders/EntryQueryBuilder.php:58-65,88-94`

- [ ] **OpenAPI `@Info` block still titled "Magic Program".**
  File: `packages/core/src/Http/Controllers/Api/Controller.php:9-13`

- [ ] **`FileUpload::validate()` carries a `@todo` to convert to Laravel validation rules** — noted here so it doesn't get lost.
  File: `packages/core/src/Field/Types/FileUpload.php:76`
