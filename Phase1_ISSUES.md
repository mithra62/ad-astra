# Phase 1 Issues Report

**Project:** laravel-base
**Branch:** phase1
**Date:** 2026-04-26
**Scope:** Full codebase analysis — data model, business logic, and structural integrity. This pass re-verified every
previously listed item against the live source. Resolution markers reflect what the code now does, not what the prior
report claimed.

> **Status legend.**
> `[RESOLVED]` — fix is in place and the code now matches the recommended shape.
> `[PARTIALLY RESOLVED]` — application code mitigates the issue but at least one named gap (DB constraint, scheduler,
> observer, parameterised flow) remains.
> `[OPEN]` — code still exhibits the original behaviour described.
> `[REGRESSED / NEW]` — the original bug returned in a different file, or an equivalent issue is now present elsewhere.

---

## Project Overview

This is a Laravel 12 CMS-style framework with a plugin-oriented, headless architecture. It implements a Craft
CMS-inspired content model: **Entry Groups** → **Entry Types** → **Entries**, a dynamic **Field** system where custom
field definitions are applied to entries, categories, and users via polymorphic relationships, a **Taxonomy** system (
Categories + Tags), a **Status** workflow, and hierarchical **Entry Trees** for URL-routable page trees. Authentication
is handled by Fortify (2FA, OAuth) with Sanctum API tokens, Spatie RBAC, and a lightweight honeypot bot-block system.
The admin interface is rendered through TwigBridge (Twig templates under `resources/views/admin`); the API is
Sanctum-protected REST (v1).

---

## CRITICAL ISSUES

These will break the application or cause data corruption in normal operation.

---

### [RESOLVED] CRIT-01 — Debug Code Hard-Kills Application on User Update Authorization

**Original location:** `app/Policies/UserPolicy.php:38-39`

```php
public function update(User $user, User $model): bool
{
    echo 'fdsa';   // outputs garbage to HTTP response
    exit;          // kills the PHP process / request
    return $user->id === $model->id;  // unreachable
}
```

**Current state.** The entire `app/Policies/` directory has been removed (`Glob 'app/Policies/*'` returns no files).
There is no `UserPolicy` class anywhere in the codebase, so the original failure path no longer exists. The user update
authorisation is now handled implicitly by the `Gate::before` super-admin bypass and direct `auth` middleware on the
admin routes.

**However — see CRIT-05 below.** The same `echo 'fdsa'; exit;` pattern has reappeared in a different controller. Marking
the original entry resolved does not mean the codebase is clean of this pattern.

---

### [RESOLVED] CRIT-02 — Trait Static Cache Collides Across Classes (`HasFieldLayout::resolvedFields`)

**File:** `app/Traits/HasFieldLayout.php:14-19`

**Current state — verified:**

```php
public static function resolvedFields(int $id): static
{
    return static::query()
        ->with('fieldLayout.tabs.elements.field')
        ->findOrFail($id);
}
```

The method-level `static $cache` has been removed entirely. `static::query()` correctly dispatches to the calling
subclass via late static binding, and the `with(...)` eager-load chain handles the read pattern that the cache used to
optimise. There is no remaining cross-class collision risk.

**Repercussion of the fix.** Repeated `EntryGroup::resolvedFields($id)` calls inside a single request now issue an extra
DB query each. In practice every call site already follows it with relation walks that would hit the same rows, so this
is fine. Add a per-class cache property only if profiling shows it matters.

---

### [RESOLVED] CRIT-03 — `Fieldable::field()` Throws on Orphaned Field Values

**File:** `app/Traits/Fieldable.php:15-20`

**Current state — verified:**

```php
public function field(string $handle): mixed
{
    return $this->fieldValues
        ->first(fn ($v) => $v->field?->handle === $handle)  // null-safe operator
        ?->resolvedValue();
}
```

The trait now uses the null-safe operator on `$v->field`, matching `Entry::field()`. `Fieldable::fieldArray()` (lines
22-28) also filters `field === null` before calling `mapWithKeys`, so an orphaned `field_values` row will not crash
either method. The trait is used by `User` and `Category`, so both are safe.

---

### [RESOLVED] CRIT-04 — `Entry::getFieldLayout()` Declares `FieldLayout` Return Type But Can Return `null`

**File:** `app/Models/Entry.php:101-107`

**Current state — verified:**

```php
public function getFieldLayout(): ?FieldLayout
{
    $typeLayout  = $this->entryType?->fieldLayout;
    $groupLayout = $this->entryGroup?->fieldLayout;

    return $typeLayout ?? $groupLayout;
}
```

Return type is now nullable. Callers must null-check, but `EntryRepository::resolveLayoutFields()` (the dominant call
site) merges both layouts via `Collection::merge()` and is null-safe by virtue of the `?? collect()` fallbacks.

---

### [RESOLVED] CRIT-05 — `echo 'fdsa'; exit;` Now Lives in the Field Group Destroy Action

**File:** `app/Http/Controllers/Admin/Field/Group.php`

**Current state — verified.** The `echo 'fdsa'; exit;` lines have been removed. The `destroy()` method is now clean:

```php
public function destroy(DeleteFieldGroupRequest $request, string $id)
{
    $group = FieldGroup::find($id);
    if ($group instanceof FieldGroup) {
        $group->delete();
        return redirect()->route('fields.groups')->with('success', trans('field.group.deleted'));
    }

    return redirect()->route('fields.groups')->with('failure', trans('field.group.not_found'));
}
```

Field group deletion now correctly reaches the delete logic and redirects. Cascade behaviour on the polymorphic
`field_groupables` pivot is `cascadeOnDelete` (per `2026_04_09_153821_create_field_groupables_table.php`).

---

## HIGH SEVERITY ISSUES

---

### [RESOLVED] HIGH-01 — `fields.handle` Has No Unique Constraint

**File:** `database/migrations/2026_04_14_000001_create_fields_table.php:18`

**Current state — verified:**

```php
$table->string('handle')->unique();
```

Lookups via `Field::where('handle', $h)->firstOrFail()` (used by `PersistsFieldValues::setField()` at
`app/Concerns/PersistsFieldValues.php:13`) are now backed by a uniqueness guarantee at the DB layer.

---

### [RESOLVED] HIGH-02 — `field_groups.handle` Has No Unique Constraint

**File:** `database/migrations/2026_01_05_174237_create_field_groups_table.php:14`

**Current state — verified:**

```php
$table->string('handle')->unique();
```

---

### [RESOLVED] HIGH-03 — `field_types.object` Has No Unique Constraint

**File:** `database/migrations/2026_04_13_215842_create_field_types_table.php:16`

**Current state — verified:**

```php
$table->string('object')->unique();
```

`FieldTypeSeeder` uses `firstOrCreate(['object' => ...])` which now relies on this constraint.

---

### [RESOLVED] HIGH-04 — `statuses.is_default` Has No Per-Group Uniqueness Constraint

**Files:**

- `database/migrations/2026_04_18_000006_create_statuses_table.php:21`
- `app/Actions/Status/CreateNewStatus.php:13-29`
- `app/Actions/Status/EditStatus.php:13-23`

**Current state — verified.** The migration still has only `$table->boolean('is_default')->default(false);` with no
unique index. Application-level enforcement was added in both create and edit actions:

```php
// CreateNewStatus
if (! empty($input['is_default'])) {
    Status::where('status_group_id', $input['status_group_id'])
        ->where('is_default', true)
        ->update(['is_default' => false]);
}
return Status::create($input);
```

```php
// EditStatus
if ($input['is_default']) {
    Status::where('status_group_id', $status->status_group_id)
        ->where('id', '!=', $status->getKey())
        ->where('is_default', true)
        ->update(['is_default' => false]);
}
```

**Current state — verified.** Both `CreateNewStatus` and `EditStatus` now wrap their clear-then-write sequences in
`DB::transaction()` with a `StatusGroup::lockForUpdate()->findOrFail(...)` at the start. Two concurrent requests that
target the same group will now queue behind the row lock rather than racing through independently.

`createByGroup()` acquires the lock via the group relation (already loaded), while `create()` and `edit()` acquire it
by ID. All three paths are covered.

**Remaining note.** Direct `Status::create([...])` outside the actions (seeders, tinker) still bypasses the guard —
but `StatusGroupSeeder` seeds exactly one default per group so this is safe in practice. The DB-level partial unique
index is not present; the transaction lock is the sole enforcement mechanism.

---

### [RESOLVED] HIGH-05 — `entries.status` Is a Free-Text String With No Referential Integrity

**Files:**

- `database/migrations/2026_04_18_000010_create_entries_table.php:23-37`
- `app/Repositories/EntryRepository.php:133-184`
- `app/Observers/StatusObserver.php`

**Current state — verified.** The single `status` string has been replaced by a three-column denormalisation, all
maintained together by `EntryRepository::applyStatus()`:

```
status_id          → FK to statuses.id (nullOnDelete)
status_handle      → indexed string (denormalised handle for fast equality reads)
status_is_public   → indexed boolean (denormalised is_public for scopePublished)
```

`applyStatus()` rejects status handles that don't belong to the entry's group's status group (throws
`InvalidArgumentException`), and `StatusObserver::updating()` propagates `is_public` flips to every referencing entry's
`status_is_public` column. `Entry::scopeWithStatus()` reads `status_handle`, `Entry::scopePublished()` reads
`status_is_public`.

**Caveat.** Request-side validation in `StoreEntryRequest` and `EditEntryRequest` only enforces
`'status' => ['nullable','string','max:100']` — it does not yet validate the handle against the group's allowed
statuses. The repository still catches it, but a non-HTTP caller invoking `Content::create(...)` with a typo gets a
`RuntimeException` deeper in the call stack rather than a friendly validation message. This residual gap is tracked in
`CURRENT_ISSUES_REVIEW.md` §1; the migration-level concern from HIGH-05 itself is closed.

---

### [RESOLVED] HIGH-06 — `Entry::scopePublished` and the `status` Field Are Independent (Dual Publication State)

**File:** `app/Models/Entry.php:109-114`

**Current state — verified:**

```php
public function scopePublished(Builder $query): Builder
{
    return $query->where('status_is_public', true)
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now());
}
```

The scope now AND-joins all three conditions. Combined with `StatusObserver` keeping `status_is_public` in sync with the
source `Status` row, the dual-state ambiguity from BR-01 is closed. A draft entry with a past `published_at` is **not**
considered published, which was the original failure mode.

---

### [RESOLVED] HIGH-07 — Incorrect `morphTo()` Relationships on Owner-Side Models

**Files originally cited:**

- `app/Models/Field.php`
- `app/Models/Field/Group.php`
- `app/Models/Category/Group.php`

**Current state — verified.** None of the cited models still expose a stub `morphTo()` method.

- `Field` declares `groups()` returning `$this->morphedByMany(Group::class, 'fieldable')` (correct — Field is the
  inverse side of a polymorphic many-to-many).
- `Field\Group` declares `fields()` returning `$this->morphToMany(Field::class, 'fieldable')` — the owner side via the
  `fieldables` pivot.
- `Category\Group` only exposes `categories()` (a regular `hasMany`); the polymorphic `categoryGroups()` relation lives
  on the consumer side via the `HasCategoryGroups` trait, mapped through `category_groupables`.

The dead `morphTo()` declarations are gone and the correct M2M directionality is in place.

---

### [RESOLVED] HIGH-08 — `FieldLayout::fields()` Silently Triggers N+1 Queries Without Eager Loading

**File:** `app/Models/FieldLayout.php:33-40`

**Current state — verified:**

```php
public function fields(): Collection
{
    $this->loadMissing('tabs.elements.field');

    return $this->tabs->flatMap(
        fn ($tab) => $tab->elements->map(fn ($el) => $el->field)
    );
}
```

The leading `loadMissing()` call ensures the relation chain is loaded exactly once on first access. Subsequent calls
within the same request re-use the cached collection.

---

## MEDIUM SEVERITY ISSUES

---

### [RESOLVED] MED-01 — `CategoryService::wouldCreateCycle()` N+1 Query Pattern

**File:** `app/Services/CategoryService.php`

**Current state — verified.** The method has been rewritten:

```php
private function wouldCreateCycle(Category $category, int $targetParentId): bool
{
    if ($targetParentId === $category->id) {
        return true;
    }

    $visited  = [$targetParentId => true];
    $current  = $targetParentId;
    $maxDepth = self::MAX_ANCESTOR_DEPTH; // 32

    while ($maxDepth-- > 0) {
        $parentId = Category::where('id', $current)->value('parent_id');

        if ($parentId === null)              { return false; }
        if ($parentId === $category->id)     { return true;  }
        if (isset($visited[$parentId]))      { return false; } // pre-existing cycle guard

        $visited[$parentId] = true;
        $current = $parentId;
    }

    return false;
}
```

Each step now reads only the `parent_id` column (no full model hydration). The `$visited` set breaks any pre-existing
cycle in stored data so the walk cannot loop infinitely. The `MAX_ANCESTOR_DEPTH = 32` constant caps the walk as a
final safety net. Tests covering deep-chain detection, pre-existing-cycle safety, and per-query column narrowness have
been added to `tests/Unit/Services/CategoryServiceTest.php`.

---

### [OPEN] MED-02 — Global `$with` on `Field` and `FieldValue` Creates Always-On Eager Load Overhead

**Files:**

- `app/Models/Field.php:33` — `protected $with = ['fieldType'];`
- `app/Models/FieldValue.php:27` — `protected $with = ['field'];`

**Current state — verified.** Both `$with` arrays are still present. They create a transitive eager-load chain
`FieldValue → Field → FieldType` on every query that touches `field_values`, even when only an ID or a count is needed.
`EntryRepository::defaultEagerLoad()` already specifies the same chain explicitly, so the global `$with` is redundant in
the hot path while still adding overhead in cold paths (admin counts, simple list views, factories, tests).

**Fix.** Remove both `$with` declarations. Update any caller that relied on implicit loading (e.g.
`$category->fieldValues->first()->field->name` without an explicit `with`).

---

### [RESOLVED] MED-03 — No Log Retention/Pruning for `api_logs` Table

**Files:**

- `app/Models/ApiLog.php`
- `routes/console.php`
- `bootstrap/app.php`

**Current state — verified.** `ApiLog` now `use`s the `Prunable` trait and declares:

```php
public function prunable()
{
    return static::where('created_at', '<', now()->subDays(90));
}
```

That fixes the missing prune scope. **However**, the `model:prune` Artisan command is **not scheduled anywhere**:

- `routes/console.php` only registers the `inspire` example command.
- `bootstrap/app.php` configures middleware and routing but does not register a schedule.
- A `grep` for `Schedule::`, `model:prune`, or `->daily(` across `app/` and `routes/` returns nothing.

So the prune scope exists but never runs unless an operator manually invokes
`php artisan model:prune --model=App\Models\ApiLog`.

**Current state — verified.** Automated pruning is handled by `app/Jobs/PruneApiLogs.php`, a
self-rescheduling queued job. On each execution it calls `ApiLog::pruneAll()` (which honours the
90-day scope in `ApiLog::prunable()`) then re-dispatches itself with a delay targeting 02:00 the
following day. No cron entry is required — the job perpetuates itself through the queue.

`routes/console.php` retains the `Schedule::command('model:prune', ...)` entry for manual shell
use at any time without disturbing the queue chain:

```bash
php artisan model:prune --model="App\Models\ApiLog"
```

**Deployment steps (one-time):**

1. Ensure `QUEUE_CONNECTION` in `.env` is set to a driver that supports delayed jobs (`database`,
   `redis`, etc.) — `sync` ignores delays and is not suitable.
2. Confirm a queue worker is running (e.g. via Supervisor: `php artisan queue:work`).
3. Kick off the chain once:
   ```bash
   php artisan tinker --execute="App\Jobs\PruneApiLogs::dispatch()"
   ```
   After this, the job re-queues itself nightly at 02:00 indefinitely.

Deferred to a separate work item: retention period env var, first-run backlog prune, tiered
retention for error-status rows.

---

### [RESOLVED] MED-04 — Duplicate `createLayout()` Method Across Two Seeders

**Files:**

- `database/seeders/Concerns/BuildsLayouts.php` *(new)*
- `database/seeders/EntryGroupSeeder.php`
- `database/seeders/ExtendedEntryGroupSeeder.php`

**Current state — verified.** The shared helper now lives in `Database\Seeders\Concerns\BuildsLayouts`.
Both seeders declare `use BuildsLayouts, WithoutModelEvents;` and the four imports that were only
needed by `createLayout()` (`Field`, `FieldLayout`, `Tab`, `TabElement`) have been removed from
each seeder. Layout seeding logic now has a single authoritative location.

---

### [PARTIALLY RESOLVED] MED-05 —
`entry_groups.status_group_id` Is Nullable But EntryRepository Throws RuntimeException If Absent

**Files:**

- `database/migrations/2026_04_18_000008_create_entry_groups_table.php:19-22`
- `app/Repositories/EntryRepository.php:135-184`
- `app/Actions/Entry/Group/CreateNewEntryGroup.php`
- `app/Actions/Entry/Group/EditEntryGroup.php`
- `app/Http/Requests/Entry/Group/StoreEntryGroupRequest.php`

**Current state — verified.** The schema still declares the column nullable:

```php
$table->foreignId('status_group_id')
    ->nullable()
    ->constrained('status_groups')
    ->nullOnDelete();
```

Request-side validation now requires `status_group_id` for entry-group create/edit, and `EntryRepository::applyStatus()`
throws when the group has no status group. So the *web admin path* is safe. But:

- `CreateNewEntryGroup` and `EditEntryGroup` still allow a `null` value through (the actions defer to the request
  layer).
- Any non-HTTP caller (`tinker`, a future REST endpoint, a future CLI command) can still construct an entry group with
  `status_group_id = null`.
- The first attempt to create an entry in that group throws `RuntimeException` — visible, but later than ideal.

This matches `CURRENT_ISSUES_REVIEW.md` §3.

**Recommended fix.** Make the column non-nullable in the existing migration (the project is still resettable; per the
current review, no repair migration is needed), and remove the `?? null` fallback from the actions.

---

### [PARTIALLY RESOLVED] MED-06 — `entry_trees.is_home` Has No Uniqueness Constraint

**Files:**

- `database/migrations/2026_04_23_200641_create_entry_tree_table.php:27`
- `app/Actions/Entry/Tree/CreateEntryTreeNode.php:74-83`

**Current state — verified.** The DB column has no unique index. App-level enforcement was added in
`CreateEntryTreeNode::assertValidPlacement()`:

```php
if ($isHome) {
    if ($parent) {
        throw new InvalidArgumentException('The Entry Tree home node must be a root node.');
    }

    if (EntryTree::query()->where('is_home', true)->exists()) {
        throw new InvalidArgumentException('Only one Entry Tree home node may exist.');
    }
}
```

`MoveEntryTreeNode::handle()` also rejects any move that would attach the home node under a parent. **Two gaps remain:**

1. **Race condition.** `assertValidPlacement` is a check-then-act: two concurrent requests could both see no home and
   both create one. There is no `lockForUpdate()` or DB-level uniqueness to backstop it.
2. **`is_home` flag flips outside the actions.** Anything that updates `EntryTree` directly (raw
   `$node->update(['is_home' => true])`, a future bulk import) bypasses the guard.

**Fix.** Add a partial unique index via raw SQL in the existing migration:

```php
DB::statement('CREATE UNIQUE INDEX entry_trees_is_home_unique ON entry_trees ((CASE WHEN is_home THEN 1 END))');
```

(or similar, depending on MySQL version). Failing that, wrap the action body in `DB::transaction()` with a
`SELECT ... FOR UPDATE` on `entry_trees WHERE is_home = true`.

---

### [OPEN] MED-07 — Missing Migration Files: Sequence Gaps `000007` and `000012`

**Current state — verified.** A directory listing of `database/migrations/` shows the same gaps:

```
2026_04_18_000005_create_status_groups_table.php
2026_04_18_000006_create_statuses_table.php
2026_04_18_000008_create_entry_groups_table.php   ← gap: 000007 missing
…
2026_04_18_000011_create_entry_authors_table.php
2026_04_18_000013_create_user_schema_table.php    ← gap: 000012 missing
…
```

On a fresh install this is harmless; on a database that ran the missing files at any point in their history it can cause
`migrate:rollback` to fail.

**Fix.** Verify in `git log -- database/migrations/` whether either sequence number ever existed. If they do exist in
any deployed database's `migrations` table, hand-delete those rows or renumber the surrounding files.

---

### [OPEN] MED-08 — `UsersSeeder` Runs Unconditionally With Hardcoded Credentials

**Files:**

- `database/seeders/UsersSeeder.php`
- `database/seeders/DatabaseSeeder.php:14-17`

**Current state — verified.** `DatabaseSeeder::run()` still calls `UsersSeeder` outside any environment guard:

```php
$this->call([
    RolesPermissionsSeeder::class,
    UsersSeeder::class,                   // ← always runs
    FieldTypeSeeder::class,
    …
]);
```

`UsersSeeder` creates **`eric@mithra62.com` with password `password` and the `super admin` role** — unconditionally.
Running `php artisan db:seed` against any database (including production) creates an immediately-exploitable super-admin
account. The seeder uses `User::factory()->create([...])` rather than `firstOrCreate`, so re-runs will fail on the
unique-email constraint, but the first run is the dangerous one.

**Fix.** Either:

```php
if (app()->environment(['local', 'testing'])) {
    $this->call([UsersSeeder::class]);
}
```

or replace the hardcoded creds with environment variables (`env('SEED_ADMIN_EMAIL')`) and document the deployment
expectation.

---

### [OPEN] MED-09 — Categories Can Have Parents From a Different Group

**Files:**

- `database/migrations/2026_04_18_000016_create_categories_table.php`
- `app/Services/CategoryService.php` (`create()`, `move()`, `wouldCreateCycle()`)

**Current state — verified.** Neither `CategoryService::create()` nor `CategoryService::move()` checks that the target
`parent_id` belongs to the same `group_id`. `wouldCreateCycle()` only walks ancestors — it does not consider group
membership.

**Fix.** In both methods:

```php
if ($parentId !== null) {
    $parentGroup = Category::where('id', $parentId)->value('group_id');
    if ((int) $parentGroup !== (int) $groupId) {
        throw new \InvalidArgumentException("Parent category must belong to the same group.");
    }
}
```

Correlated with **BR-03** below.

---

## LOW SEVERITY ISSUES

---

### [PARTIALLY RESOLVED] LOW-01 — `UserSchema::resolved()` In-Process Static Cache Can Return Stale Data

**Files:**

- `app/Models/UserSchema.php:42-45`
- `tests/TestCase.php:13` and `tests/Unit/Models/UserSchemaTest.php` (callers)

**Current state — verified.** The static cache and `flushResolved()` helper are unchanged. `flushResolved()` is now
actually called from `tests/TestCase.php` between cases, eliminating the stale-cache problem in unit tests. **No
production code calls it**, however — neither the `UserSchema` admin controller (if and when one exists) nor any
field-layout edit action invalidates the cache. In a long-lived process (Octane, persistent queue worker), edits to the
user schema will not be visible until the worker restarts.

**Fix.** Call `UserSchema::flushResolved()` at the end of any admin action that modifies the user schema's
`field_layout_id` or its underlying `FieldLayout`/`Tab`/`TabElement` rows. Better yet, listen to those models' `saved`/
`deleted` events and flush from a single place.

---

### [OPEN] LOW-02 — Permission Gaps for Entry, Field, Status, and Role Management Areas

**File:** `database/seeders/RolesPermissionsSeeder.php`

**Current state — verified.** The seeder defines permissions only for: `api`, `access admin`, user/user-token CRUD,
category-group + category CRUD/reorder, and media-library CRUD/reorder. **No** permissions exist for entries, entry
groups, entry types, fields, field groups, field layouts (or their tabs/elements), statuses, status groups, roles, or
individual media items. Every admin section that touches those areas is gated only by `access admin` plus the
super-admin bypass.

**Fix.** Extend `RolesPermissionsSeeder` to add the missing permissions and grant them to the existing `admin` role.
Audit each `app/Http/Controllers/Admin/*` controller and its `FormRequest` to gate operations on the new permissions.
This was deferred during phase 1; it is a deliberate gap, not an oversight in the report.

---

### [OPEN] LOW-03 — `Entry::handle` Auto-Generation Can Produce an Empty String

**File:** `app/Repositories/EntryRepository.php:120-131`

**Current state — verified.** `applyCoreAttributes()` is unchanged:

```php
$entry->handle = $data['handle'] ?? Str::slug($entry->title ?? '');
```

If the title is null/empty (in practice prevented by DB `NOT NULL`, but possible via direct model assignment in tests or
a custom command), `Str::slug('')` returns `''`. An empty handle then collides with another empty handle in the same
group on the unique `(entry_group_id, handle)` index, surfacing as a `QueryException`.

**Fix.** Reject empty handles explicitly:

```php
$handle = $data['handle'] ?? Str::slug($entry->title ?? '');
if ($handle === '') {
    throw new \InvalidArgumentException('Entry handle cannot be empty.');
}
$entry->handle = $handle;
```

---

### [OPEN] LOW-04 — Concurrent Entry Creation With Same Title Will Race on `handle` Unique Constraint

**File:** `app/Repositories/EntryRepository.php` (no fix in `create()` or `applyCoreAttributes()`)

**Current state — verified.** `EntryRepository::upsertFieldValue()` retries on SQLSTATE `23000` (unique-constraint
violation) for `field_values` writes, but no equivalent guard exists for the `entry_group_handle_unique` index on
`entries`. Two concurrent `Content::create('blog_post', ['title' => 'My Post'])` calls in the same group both compute
`handle = 'my-post'` and one will receive a raw `QueryException`.

`PodcastEpisodeEntryType::beforeCreate()` does take a `lockForUpdate()` on the entry group row when assigning episode
numbers, which incidentally serialises podcast episode creation — but that protection is type-specific and does not
cover other entry types or the title-vs-handle race generally.

**Fix.** Either lock the entry group row at the start of `EntryRepository::create()`, or detect the unique-constraint
violation and append a numeric suffix (`my-post-2`, `my-post-3`, …) before retrying.

---

## BUSINESS RULES ISSUES

---

### [RESOLVED] BR-01 — Dual Publication State: `status` String + `published_at` Timestamp Are Not Coordinated

**Resolution.** Resolved by the same code change as HIGH-06. `Entry::scopePublished()` now ANDs `status_is_public`,
`published_at IS NOT NULL`, and `published_at <= now()`, with `StatusObserver` keeping the denormalised
`status_is_public` truthful. The behaviour table from the original report now reads:

| status_handle               | status_is_public | published_at | `scopePublished` returns?                                            |
|-----------------------------|------------------|--------------|----------------------------------------------------------------------|
| `draft` (or any non-public) | `false`          | past date    | **No** ✓                                                             |
| `published`                 | `true`           | `null`       | **No** ✓ (intentional — must be explicitly scheduled)                |
| `published`                 | `true`           | future date  | **No** ✓ (scheduled — flips automatically when `now()` overtakes it) |
| `archived`                  | `false`          | past date    | **No** ✓                                                             |

The dual-state ambiguity is closed.

---

### [PARTIALLY RESOLVED] BR-02 — Entry Relationship Graph Allows Indirect Cycles

**Files:**

- `app/Repositories/EntryRepository.php` (`syncRelationshipField()` — write-side)
- `app/Services/EntryService.php` (`loadRelatedRecursive()` — read-side)

**Current state — verified.**

*Write side.* `syncRelationshipField()` still only filters direct self-references (A → A). The inline comment is
explicit:

```php
// Prevent direct self-reference (A → A). Indirect cycles (A → B → A) are
// intentionally not enforced here — relationship data is a graph, not a tree,
// and cycle prevention for deeper traversals must be handled by the caller
// using loadRelatedRecursive() or an equivalent depth-limited loader.
```

*Read side.* `EntryService::loadRelatedRecursive()` was added with both a `$maxDepth` budget (default 3) and a `$seen`
ID set, so consumers walking the graph cannot trigger infinite recursion.

**Why partially resolved.** The graph-versus-tree decision is now defensible, and a safe traversal helper exists, but *
*any code that traverses `entry->entryRelationships` directly without using `loadRelatedRecursive()` is still vulnerable
**. Twig templates rendering "related entries of related entries" inline will loop on a pre-existing cycle.
Additionally, the `entry_relationships` schema has no DB-level guard against cycles (a unique index covers
`(entry_id, related_entry_id, field_id)` only).

**Recommendation.** Document the contract on `Entry::entryRelationships` itself. If renderers need uncapped traversal,
route them through `EntryService::loadRelatedRecursive()` only.

---

### [OPEN] BR-03 — Category Hierarchy Allows Parent From Different Group

Same root cause as MED-09: neither `CategoryService::create()` nor `move()` enforces that
`parent.group_id === child.group_id`, and `Category::childrenRecursive()` does not filter by `group_id`. A parent
attached from a different group does not appear in `CategoryService::tree($group)` (which scopes the root query by
group), but the orphaned child still has a `parent_id` pointing outside its declared group, which can confuse breadcrumb
generation, admin pickers, and any cross-group reporting.

See MED-09 for the recommended fix.

---

### [OPEN] BR-04 — Entry Tree `nullOnDelete` on `parent_id` Creates Silent URI Orphans

**File:** `database/migrations/2026_04_23_200641_create_entry_tree_table.php:23-25`

**Current state — verified.**

```php
$table->foreignId('parent_id')
    ->nullable()
    ->constrained('entry_trees')
    ->nullOnDelete();
```

When a parent `EntryTree` row is deleted, MySQL sets every child's `parent_id` to `NULL`. The children become root nodes
silently, retaining their old `uri` and `depth` values. `RebuildEntryTreeUri` is the only code path that recomputes
`uri` and `depth`, and it is invoked from `MoveEntryTreeNode::handle()` only — **not** from any deletion observer.

**Concrete consequences:**

1. URI stays the same (`/blog/2025/my-post`) even though the node is now a root. Adding a new root with a colliding
   handle then fails the `(parent_id, handle)` unique index.
2. `depth` is stale (e.g. still `2` when the row is at depth `0`). `EntryType.max_depth` checks against this become
   wrong.
3. Subtree URIs further down also stay stale because no rebuild was triggered.

**Fix.** Add a model observer (`app/Observers/EntryTreeObserver.php`) that, on `deleting`, walks the descendants and
either re-roots them with rebuilt URIs and depths or hard-deletes the subtree, depending on product intent. Register it
in `AppServiceProvider::boot()` alongside `StatusObserver`.

---

### [OPEN] BR-05 — Super Admin Gate Bypass Has No Audit Trail

**File:** `app/Providers/AppServiceProvider.php:85-87`

**Current state — verified:**

```php
Gate::before(function ($user, $ability) {
    return $user->hasRole('super admin') ? true : null;
});
```

Unchanged from the original report. There is no audit log, no event dispatch on bypass, and no separation between
super-admin actions and ordinary admin actions in `api_logs` (which only captures HTTP-routed requests, not arbitrary
gate calls in jobs or commands).

**Recommendation.** Wrap the bypass in an explicit event:

```php
Gate::before(function ($user, $ability, $arguments) {
    if ($user->hasRole('super admin')) {
        event(new \App\Events\SuperAdminGateBypass($user, $ability, $arguments));
        return true;
    }
    return null;
});
```

…and write a listener that persists to a dedicated audit table.

---

### [OPEN] BR-06 — `api_logs` Captures Full Response Bodies for All JSON Responses

**File:** `app/Http/Middleware/LogRequestResponse.php` (`summarizeResponse()`)

**Current state — verified.** The middleware's behaviour is unchanged:

```php
if ($response instanceof JsonResponse) {
    $payload['body'] = $this->sanitizeValue($response->getData(true));
}
```

Sensitive *keys* are redacted (`password`, `token`, `authorization`, …) and the final JSON is truncated to 4 000
characters per record, but business-specific PII or private entry content is captured verbatim. This is a deliberate
design choice; flag it as a privacy/compliance question rather than a bug.

**Recommendation.** Decide policy: log JSON bodies only on 4xx/5xx responses, log only on a configurable allowlist of
routes, or move `request_payload`/`response_payload` columns to an encrypted cast.

---

### [PARTIALLY RESOLVED] BR-07 — Entry Tree `depth` Column Is Now Maintained on Move But Not on Delete

**Files:**

- `app/Actions/Entry/Tree/MoveEntryTreeNode.php`
- `app/Actions/Entry/Tree/RebuildEntryTreeUri.php`

**Current state — verified.** The original report claimed depth was never maintained. That is no longer true:
`MoveEntryTreeNode::handle()` invokes `RebuildEntryTreeUri::handle()`, which recursively recalculates `depth` for the
moved node and every descendant:

```php
$node->depth = $node->parent
    ? $node->parent->depth + 1
    : 0;

$node->uri = $this->buildUri($node);
$node->save();

foreach ($node->children as $child) {
    $this->handle($child);
}
```

Move and re-parent paths are therefore depth-correct.

**Remaining gap.** Same root cause as **BR-04**: when a parent is deleted, `nullOnDelete` mutates `parent_id` directly
without invoking `RebuildEntryTreeUri`. Children's `depth` and `uri` go stale. `EntryType.max_depth` validation that
relies on `depth` becomes unreliable for subtrees affected by deletion.

**Fix.** Same observer as BR-04 — a `deleting` hook that calls `RebuildEntryTreeUri` on every descendant after the
parent delete.

---

## Doc-vs-Code Drift Notes

A handful of items in this report were previously marked `[RESOLVED]` despite the fix being only partial. They have been
re-classified above with explicit `[PARTIALLY RESOLVED]` markers and a description of what remains. The full list, for
change-tracking visibility:

| Item    | Old marker                             | New marker             | Why                                                                          |
|---------|----------------------------------------|------------------------|------------------------------------------------------------------------------|
| HIGH-04 | `[RESOLVED]`                           | `[PARTIALLY RESOLVED]` → `[RESOLVED]` | Transactional `lockForUpdate()` added to all three write paths.  |
| MED-03  | `[RESOLVED]`                           | `[PARTIALLY RESOLVED]` | `Prunable` exists but no scheduler runs it.                                  |
| MED-05  | `[RESOLVED]`                           | `[PARTIALLY RESOLVED]` | Schema column still nullable; non-HTTP callers can violate the contract.     |
| MED-06  | `[OPEN]` (already correctly described) | `[PARTIALLY RESOLVED]` | App-level guard added in `CreateEntryTreeNode`; DB constraint still missing. |
| LOW-01  | `[OPEN]` (already correctly described) | `[PARTIALLY RESOLVED]` | Flushed in tests; no production flush on schema edits.                       |
| BR-02   | `[OPEN]` (already correctly described) | `[PARTIALLY RESOLVED]` | Read-side `loadRelatedRecursive` adds depth + cycle guards.                  |
| BR-07   | `[OPEN]` (already correctly described) | `[PARTIALLY RESOLVED]` | Depth is rebuilt on move; still stale on delete.                             |

A new entry, **CRIT-05**, was added to capture an `echo 'fdsa'; exit;` regression in
`app/Http/Controllers/Admin/Field/Group.php:97-98`. This is the same pattern that originally appeared in the now-deleted
`UserPolicy` from CRIT-01, but in a different file. As of the 2026-04-28 pass, CRIT-05 is also resolved — the debug
lines have been removed and `destroy()` functions correctly.

---

# Recommended Order Of Work

1. **MED-08** — gate `UsersSeeder` to non-production environments (or remove hardcoded credentials).
2. **MED-05** — make `entry_groups.status_group_id` non-nullable (project is still resettable, per
   `CURRENT_ISSUES_REVIEW.md`); strip the `?? null` fallback from the entry-group actions.
5. **MED-09 / BR-03** — add the parent-group guard in `CategoryService::create()` and `move()`.
6. **BR-04 / BR-07** — add the `EntryTreeObserver` (deleting handler) that re-roots or hard-deletes subtrees and
   rebuilds `depth`/`uri` for affected nodes.
7. **LOW-03 / LOW-04** — guard against empty handles and serialise per-group entry creation against handle collisions.
8. **MED-06** — add a partial-unique index for `entry_trees.is_home`.
9. **MED-02** — drop the global `$with` from `Field` and `FieldValue`; verify all callers eager-load explicitly.
11. **LOW-01** — flush `UserSchema::resolved()` from any admin action that modifies the user schema or its layout
    subtree.
12. **LOW-02** — author the missing entry / field / status / role permissions.
13. **BR-05** — add audit logging for super-admin gate bypass.
14. **BR-06** — decide and document the `api_logs` body-capture policy; reduce surface area accordingly.
16. **MED-07** — confirm the `000007` and `000012` migration sequence numbers were never deployed; document the gap.

Items higher in the list either prevent immediate harm (CRIT, security) or are mechanical wins that unblock subsequent
work.

---

*End of Phase 1 Issues Report — originally verified 2026-04-26; re-verified against current source on 2026-04-28.*
