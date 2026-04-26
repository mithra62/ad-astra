# Phase 1 Issues Report

**Project:** laravel-base  
**Branch:** phase1  
**Date:** 2026-04-25  
**Scope:** Full codebase analysis — data model, business logic, and structural integrity

---

## Project Overview

This is a Laravel 12 CMS-style framework with a plugin-oriented, headless architecture. It implements a Craft CMS-inspired content model: **Entry Groups** → **Entry Types** → **Entries**, a dynamic **Field** system where custom field definitions are applied to entries, categories, and users via polymorphic relationships, a **Taxonomy** system (Categories + Tags), a **Status** workflow, and hierarchical **Entry Trees** for URL-routable page trees. Authentication is handled by Fortify (2FA, OAuth) with Sanctum API tokens, Spatie RBAC, and a lightweight honeypot bot-block system. The admin interface is a full Blade-rendered dashboard; the API is Sanctum-protected REST (v1).

---

## CRITICAL ISSUES

These will break the application or cause data corruption in normal operation.

---

### [RESOLVED] CRIT-01 — Debug Code Hard-Kills Application on User Update Authorization

**File:** `app/Policies/UserPolicy.php:38-39`

```php
public function update(User $user, User $model): bool
{
    echo 'fdsa';   // outputs garbage to HTTP response
    exit;          // kills the PHP process / request
    return $user->id === $model->id;  // unreachable
}
```

Any action that hits `Gate::allows('update', $user)` — including the admin user edit flow — will output `fdsa` to the raw response and immediately terminate the request. This affects every environment, including production once deployed. The actual comparison logic below the `exit` is dead code.

**Fix:** Remove lines 38–39. The intended logic `return $user->id === $model->id;` is correct and should be the only statement.

**Repercussion:** None beyond removing debug output. The policy will then work as intended.

---

### [RESOLVED] CRIT-02 — Trait Static Cache Collides Across Classes (`HasFieldLayout::resolvedFields`)

**File:** `app/Traits/HasFieldLayout.php:16-23`

```php
public static function resolvedFields(int $id): static
{
    static $cache = [];   // <-- PHP method-level static: shared across ALL classes using this trait

    return $cache[$id] ??= static::query()
        ->with('fieldLayout.tabs.elements.field')
        ->findOrFail($id);
}
```

In PHP, `static $cache` declared inside a **trait method** is a single shared variable for all classes that include the trait — it is **not** per-class. `EntryGroup`, `EntryType`, `Category\Group`, and `UserSchema` all use `HasFieldLayout`. If `EntryGroup::resolvedFields(1)` runs first, it stores an `EntryGroup` in `$cache[1]`. When `EntryType::resolvedFields(1)` is called next, PHP returns the cached `EntryGroup` record instead of an `EntryType` — a wrong-type object with wrong data is silently returned.

**Example scenario:**
```php
$group = EntryGroup::resolvedFields(1);   // Stores EntryGroup #1 in shared $cache[1]
$type  = EntryType::resolvedFields(1);    // Returns EntryGroup #1 — wrong model, wrong data
```

**Fix:** Replace the method-level static with a class-level static property (per-class via LSB), or simply remove the cache entirely and rely on Laravel's relation eager-loading, which is already applied everywhere this is called.

```php
// In each class (or via a properly structured trait property):
protected static array $resolvedCache = [];

public static function resolvedFields(int $id): static
{
    return static::$resolvedCache[$id] ??= static::query()
        ->with('fieldLayout.tabs.elements.field')
        ->findOrFail($id);
}
```

**Repercussion:** Removing the in-process cache increases DB queries for repeated calls within a single request. In most web contexts this is negligible; add per-class cache properties if profiling shows it matters.

---

### [RESOLVED] CRIT-03 — `Fieldable::field()` Throws on Orphaned Field Values (vs `Entry::field()` Which Is Safe)

**File:** `app/Traits/Fieldable.php:16-19`

```php
// UNSAFE — will throw TypeError if $v->field is null
public function field(string $handle): mixed
{
    return $this->fieldValues
        ->first(fn ($v) => $v->field->handle === $handle)  // no null-safe operator
        ?->resolvedValue();
}
```

Compare with the correct implementation in `Entry::field()` (app/Models/Entry.php:76):

```php
// SAFE — null-safe operator prevents crash
$fv = $this->fieldValues->first(fn ($v) => $v->field?->handle === $handle);
```

The `Fieldable` trait is used by `Category` and `User`. If any `field_values` row has a `field_id` that no longer exists in the `fields` table (orphaned via direct DB manipulation or a bug), calling `->field('handle')` on a Category or User will throw a `TypeError` ("cannot access property of null"). The `fields.field_id` FK cascades deletes, so this is unlikely — but the discrepancy between the trait and the model implementation is itself a defect.

**Fix:** Change `$v->field->handle` to `$v->field?->handle` in `Fieldable::field()`.

---

### [RESOLVED] CRIT-04 — `Entry::getFieldLayout()` Declares `FieldLayout` Return Type But Can Return `null`

**File:** `app/Models/Entry.php:91-97`

```php
public function getFieldLayout(): FieldLayout  // return type says non-nullable
{
    $typeLayout  = $this->entryType?->fieldLayout;  // can be null
    $groupLayout = $this->entryGroup?->fieldLayout; // can be null

    return $typeLayout ?? $groupLayout;  // returns null if both are null
}
```

`entry_types.field_layout_id` is nullable and `entry_groups.field_layout_id` is nullable. Any code calling `getFieldLayout()` and expecting a concrete `FieldLayout` will receive `null`, causing downstream `TypeError` or `Call to a member function on null` exceptions. This includes `FieldLayout::fields()` which is called throughout the rendering pipeline.

**Fix:** Change the return type to `?FieldLayout`:

```php
public function getFieldLayout(): ?FieldLayout
```

All callers must then null-check before proceeding. Alternatively, throw an explicit exception when the layout is missing so the failure is visible rather than silent.

---

## HIGH SEVERITY ISSUES

These will not immediately crash normal flows but represent data integrity failures, silent bugs, or conditions that will cause hard-to-diagnose problems as data grows.

---

### [RESOLVED] HIGH-01 — `fields.handle` Has No Unique Constraint

**File:** `database/migrations/2026_04_14_000001_create_fields_table.php:18`

```php
$table->string('handle')->index();   // only indexed, NOT unique
```

Two fields can have the same handle. `PersistsFieldValues::setField()` (app/Concerns/PersistsFieldValues.php:13) resolves a field by handle:

```php
$field = Field::where('handle', $handle)->firstOrFail();
```

If two fields share the handle `body`, this silently returns whichever MySQL returns first (ordering is non-deterministic without ORDER BY). Field values would be written to the wrong field with no error. The `CategoryGroupSeeder` and `UserSchemaSeeder` both call `Field::firstOrCreate(['handle' => ...])` which would correctly find the existing record — but direct DB or Admin creation doesn't prevent duplicates.

**Fix:** Add a unique constraint to the migration:
```php
$table->string('handle')->unique();
```

**Repercussion:** Re-seeding or migrating requires verifying no duplicate handles exist in the `fields` table. With two seeders, `FieldGroupSeeder` and `UserSchemaSeeder` both create fields with distinct handles so no conflict exists in seed data.

---

### [RESOLVED] HIGH-02 — `field_groups.handle` Has No Unique Constraint

**File:** `database/migrations/2026_01_05_174237_create_field_groups_table.php:14`

```php
$table->string('handle')->index();   // only indexed, NOT unique
```

Multiple field groups can share the same handle. The seeders use `firstOrCreate(['handle' => ...])` which would find the first match, but the admin UI can create duplicate groups. Duplicate handles break any lookup-by-handle logic.

**Fix:** Change to `->unique()`.

---

### [RESOLVED] HIGH-03 — `field_types.object` Has No Unique Constraint

**File:** `database/migrations/2026_04_13_215842_create_field_types_table.php`

The `field_types` table has no unique constraint on the `object` column. Two rows could map to the same PHP class (`App\Field\Types\Text::class`). `FieldTypeSeeder` uses `firstOrCreate(['object' => ...])` which is safe, but there is no database-level guard. If a duplicate is created, `Field::fieldType->instance()` would return whichever type the ORM picks — silently using the wrong type configuration.

**Fix:** Add `$table->string('object')->unique();` to the migration.

---

### [RESOLVED] HIGH-04 — `statuses.is_default` Has No Per-Group Uniqueness Constraint

**File:** `database/migrations/2026_04_18_000006_create_statuses_table.php`

```php
$table->boolean('is_default')->default(false);
// No constraint prevents multiple is_default=true in the same group
```

`EntryRepository::applyStatus()` (app/Repositories/EntryRepository.php:170) uses:

```php
$default = $statusGroup->statuses->firstWhere('is_default', true);
```

If a status group has two statuses with `is_default=true`, `firstWhere` returns whichever is ordered first by `sort_order`. Newly created entries will receive an arbitrarily chosen default. The admin can currently set multiple defaults with no DB error.

**Fix:** There is no single-column partial unique constraint syntax in standard SQL. The correct approach is an application-level validation in the seeder and admin form request, plus a DB-level check constraint (MySQL 8.0.16+):

```sql
-- Option A (MySQL 8+): check constraint allowing only one default per group
-- Or enforced via unique index on a computed value.
```

A practical fix is adding a unique partial index via a raw migration statement:

```php
$table->unique(['status_group_id', 'is_default'], 'status_group_default_unique');
// This enforces only one (group_id, is_default=true) combination is allowed.
// NOTE: MySQL UNIQUE treats multiple NULL as distinct, so (group, false) pairs
// need careful handling. Consider an application-level guard instead.
```

The most reliable fix is enforcing it in form requests and in the seeder, and adding application logic that resets `is_default=false` on other statuses in the same group before setting a new default.

---

### [RESOLVED] HIGH-05 — `entries.status` Is a Free-Text String With No Referential Integrity

**File:** `database/migrations/2026_04_18_000010_create_entries_table.php:28`

```php
$table->string('status')->nullable()->index();
// No FK to statuses.handle; any string can be written
```

The `status` column stores a handle string like `'draft'` or `'published'`. When a `Status` record is deleted from the `statuses` table, all entries with that status value become orphaned — they hold a handle that no longer maps to any status record. The admin interface can display these entries with an unresolvable status.

Additionally, direct Eloquent model updates (bypassing `EntryRepository::applyStatus()`) can set any arbitrary string. For example:

```php
$entry->status = 'foobar';
$entry->save();   // succeeds — no DB validation
```

**Fix:** Application-level: enforce all writes through the repository. DB-level: add a trigger or application-side validation on save. There is no FK from string to string in standard SQL, so this must be enforced by convention and validation.

A migration-based approach would be to add a composite index and ensure entry creation always goes through the repository. Also add a form request validation rule that resolves the handle against the group's status list.

---

### [RESOLVED] HIGH-06 — `Entry::scopePublished` and the `status` Field Are Independent (Dual Publication State)

**File:** `app/Models/Entry.php:99-103`

```php
public function scopePublished(Builder $query): Builder
{
    return $query->whereNotNull('published_at')
        ->where('published_at', '<=', now());
    // Does NOT check status — a Draft entry with past published_at is "published"
}
```

An entry with `status = 'draft'` and `published_at = '2025-01-01'` will be returned by `scopePublished()`. There are now two independent mechanisms controlling whether an entry is "live": the `status` string and the `published_at` timestamp. Neither system coordinates with the other.

This is a business logic issue that will cause content to appear publicly before an editor considers it ready.

See the **Business Rules** section for a detailed discussion.

---

### [RESOLVED] HIGH-07 — `Field::fieldable()` Is a Logically Incorrect `morphTo()` Relationship

**File:** `app/Models/Field.php:45-48`

```php
public function fieldable()
{
    return $this->morphTo();
}
```

The `Field` model does not have `fieldable_type` or `fieldable_id` columns — the `fields` table schema has no such columns. `morphTo()` will attempt to read these non-existent attributes and return `null` or throw depending on context. This is dead code that will mislead developers.

The same pattern appears in two other models:

**`app/Models/Field/Group.php:25-28`:**
```php
public function field_groupable()
{
    return $this->morphTo();  // field_groups table has no field_groupable_* columns
}
```

**`app/Models/Category/Group.php:28-30`:**
```php
public function category_groupable()
{
    return $this->morphTo();  // category_groups table has no category_groupable_* columns
}
```

In all three cases, the model is the "owner" side of a polymorphic many-to-many (via pivot tables `fieldables`, `field_groupables`, `category_groupables`), not the polymorphic "child" side that `morphTo()` represents.

**Fix:** Remove all three `morphTo()` methods. The correct inverse relationships are already defined (`fields()`, `groups()`, etc.) and the pivot tables handle polymorphism correctly.

---

### HIGH-08 — `FieldLayout::fields()` Silently Triggers N+1 Queries Without Eager Loading

**File:** `app/Models/FieldLayout.php:33-37`

```php
public function fields(): Collection
{
    return $this->tabs->flatMap(          // lazy-loads tabs if not eager-loaded
        fn ($tab) => $tab->elements->map( // lazy-loads elements for each tab
            fn ($el) => $el->field        // lazy-loads field for each element
        )
    );
}
```

If `FieldLayout` is retrieved without eager-loading `tabs.elements.field`, this triggers:
- 1 query to load tabs
- N queries (one per tab) to load elements
- M queries (one per element) to load fields

For a layout with 3 tabs and 5 fields each = 19 queries. This method is called in `EntryRepository::resolveLayoutFields()`, `Entry::getFieldLayout()->fields()`, `CategoryService::resolveFields()`, and `FieldService` paths.

The `defaultEagerLoad()` in `EntryRepository` does include `entryGroup.fieldLayout.tabs.elements.field.fieldType`, which prevents the issue there. But any code path that loads a `FieldLayout` independently and calls `fields()` will hit N+1.

**Fix:** Assert that `tabs` is loaded in the method, or document the required eager-load contract explicitly. Alternatively, add `$this->loadMissing('tabs.elements.field')` at the top of `fields()`:

```php
public function fields(): Collection
{
    $this->loadMissing('tabs.elements.field');
    return $this->tabs->flatMap(fn ($tab) => $tab->elements->map(fn ($el) => $el->field));
}
```

---

## MEDIUM SEVERITY ISSUES

These are structural problems that won't break normal operation today but will cause maintenance problems, performance degradation, or subtle bugs as the system grows.

---

### MED-01 — `CategoryService::wouldCreateCycle()` N+1 Query Pattern

**File:** `app/Services/CategoryService.php:90-107`

```php
private function wouldCreateCycle(Category $category, int $targetParentId): bool
{
    if ($targetParentId === $category->id) {
        return true;
    }

    $candidate = Category::find($targetParentId);

    while ($candidate?->parent_id !== null) {         // One DB query per loop iteration
        if ($candidate->parent_id === $category->id) {
            return true;
        }
        $candidate = Category::find($candidate->parent_id);  // N+1 per ancestor level
    }

    return false;
}
```

For a category hierarchy 10 levels deep, this executes up to 10 individual `SELECT` queries. The seed data only has 2 levels, so this is not currently visible, but it will degrade for any real-world taxonomy.

**Fix:** Load the entire ancestor chain in a single recursive CTE query, or use a closure table pattern. A simpler immediate fix is to load ancestors by collecting all parent IDs first:

```php
private function wouldCreateCycle(Category $category, int $targetParentId): bool
{
    $visited = [$targetParentId];
    $parentId = Category::where('id', $targetParentId)->value('parent_id');

    while ($parentId !== null) {
        if ($parentId === $category->id) {
            return true;
        }
        if (in_array($parentId, $visited, true)) {
            return true; // existing cycle in data
        }
        $visited[] = $parentId;
        $parentId = Category::where('id', $parentId)->value('parent_id');
    }

    return false;
}
```

This still has N+1 but is structurally clearer. A CTE-based approach would be optimal.

---

### MED-02 — Global `$with` on `Field` and `FieldValue` Creates Always-On Eager Load Overhead

**File:** `app/Models/Field.php:33` and `app/Models/FieldValue.php:27`

```php
// Field.php
protected $with = ['fieldType'];  // Every Field load automatically loads FieldType

// FieldValue.php
protected $with = ['field'];      // Every FieldValue load automatically loads Field (which loads FieldType)
```

These create a three-level always-on eager load chain: `FieldValue → Field → FieldType`. Every query that loads field values — including the full entry eager-load chain in `EntryRepository::defaultEagerLoad()` — loads this entire chain. Bulk operations that only need a count or a specific column of `FieldValue` still pay the full join cost.

This is particularly expensive in admin listing views and API endpoints that fetch many entries.

**Fix:** Remove `$with` from both models and explicitly specify eager loads at the query site:

```php
// At query time in EntryRepository:
'fieldValues.field.fieldType'
```

This is already done in `EntryRepository::defaultEagerLoad()` — the global `$with` is redundant and only adds overhead.

**Repercussion:** Any code path that loads `FieldValue` without specifying the `field` relation (e.g., `$category->fieldValues` without eager loading) will no longer auto-load the field. Every caller must add the explicit relation. This is the correct behavior.

---

### [RESOLVED] MED-03 — No Log Retention/Pruning for `api_logs` Table

**File:** `app/Http/Middleware/LogRequestResponse.php`

Every authenticated API request inserts a row into `api_logs`. There is no TTL column, no scheduled purge command, no partitioning strategy, and no size cap visible anywhere in the codebase. In an active API environment, this table grows indefinitely.

The `LogRequestResponse` middleware is applied to all `api.php` routes. For APIs under moderate traffic (e.g., 100 req/min), the table would accumulate ~4.3 million rows per month.

**Fix:** Add a `prunable()` method to the `ApiLog` model and schedule the `model:prune` Artisan command, or add a queued cleanup job:

```php
// In ApiLog.php
use Illuminate\Database\Eloquent\Prunable;

class ApiLog extends Model
{
    use Prunable;

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
```

---

### MED-04 — Duplicate `createLayout()` Method Across Two Seeders

**Files:**  
- `database/seeders/EntryGroupSeeder.php:113-143`  
- `database/seeders/ExtendedEntryGroupSeeder.php:298-327`

Identical private `createLayout(string $name, array $tabs): FieldLayout` methods exist in both seeders. The implementations are functionally the same — both create a `FieldLayout`, iterate tabs, and create `TabElement` records. The only difference is that `ExtendedEntryGroupSeeder::createLayout()` has the comment "Fields that don't exist in the database are silently skipped" while `EntryGroupSeeder::createLayout()` does not comment this (but the behavior is the same — `$field = Field::where('handle', $handle)->first()` followed by `if (! $field) { continue; }`).

Any future change to layout seeding logic requires updating both files.

**Fix:** Extract `createLayout()` into a shared seeder trait or a dedicated `SeederHelper` class. Alternatively, have `ExtendedEntryGroupSeeder` call the parent or an inherited method.

---

### MED-05 — `entry_groups.status_group_id` Is Nullable But EntryRepository Throws RuntimeException If Absent

**File:** `database/migrations/2026_04_18_000008_create_entry_groups_table.php:19-21`  
**File:** `app/Repositories/EntryRepository.php:138-143`

```php
// Migration: status_group_id is optional
$table->foreignId('status_group_id')
    ->nullable()
    ->constrained('status_groups')
    ->nullOnDelete();

// Repository: throws if missing
$statusGroup = $entry->entryGroup?->statusGroup;
if (! $statusGroup) {
    throw new \RuntimeException(
        "EntryGroup [{$entry->entryGroup?->handle}] has no status group configured."
    );
}
```

An admin can create an `EntryGroup` without a status group (the field is nullable). Attempting to create any entry in that group will throw a `RuntimeException`. This is a schema–code contract mismatch: the database permits the absence of a status group, but the application requires one. The error will surface unexpectedly in production.

**Fix:** Either make `status_group_id` non-nullable in the migration (enforce the contract at the DB level), or handle the missing status group gracefully in the repository — such as skipping status assignment and leaving it null.

---

### MED-06 — `entry_trees.is_home` Has No Uniqueness Constraint

**File:** `database/migrations/2026_04_23_200641_create_entry_tree_table.php:27`

```php
$table->boolean('is_home')->default(false);
// No unique constraint prevents multiple home entries
```

Multiple `EntryTree` records can have `is_home=true`. Any routing logic that searches for `where('is_home', true)->first()` would return an arbitrary result, making the home page non-deterministic.

**Fix:** Add a unique partial index. In MySQL:

```php
// In migration or a new migration:
DB::statement('CREATE UNIQUE INDEX entry_trees_is_home_unique ON entry_trees (is_home) WHERE is_home = 1');
```

Or enforce it at the application layer in the service that creates/updates entry trees by setting all other `is_home` values to false before setting the new one.

---

### MED-07 — Missing Migration Files: Sequence Gaps `000007` and `000012`

Reviewing the migration file list sorted by filename:

```
2026_04_18_000006_create_statuses_table.php
2026_04_18_000008_create_entry_groups_table.php   ← gap: 000007 missing
2026_04_18_000011_create_entry_authors_table.php
2026_04_18_000013_create_user_schema_table.php    ← gap: 000012 missing
```

Two migration sequence numbers (`000007` and `000012`) are absent from the `database/migrations/` directory. Since these use timestamp-based naming (not auto-increment names), gaps may indicate migrations that were deleted after being committed to version control. On a fresh install the gaps have no functional impact, but in any environment that ran the deleted migrations (and has a `migrations` table entry for them), re-running `migrate:fresh` or rolling back across that range will produce errors.

**Fix:** Verify in git history whether migrations for these sequence numbers ever existed. If so, ensure they are not referenced in the `migrations` table on any deployed database. If they are phantom numbers (never created), the gaps are harmless.

---

### MED-08 — `UsersSeeder` Runs Unconditionally With Hardcoded Credentials

**File:** `database/seeders/UsersSeeder.php` (creates `eric@mithra62.com` / `password`)  
**File:** `database/seeders/DatabaseSeeder.php:12`

```php
$this->call([
    RolesPermissionsSeeder::class,
    UsersSeeder::class,      // ← always runs, not gated by environment
    ...
]);
```

`EntrySeeder` and `FakeDataSeeder` are correctly gated to `local` and `testing` environments. `UsersSeeder` is not. If `db:seed` is run on a staging or production database (e.g., during a fresh deploy), it creates an admin user with email `eric@mithra62.com` and password `password` — an immediately exploitable credential.

The seeder uses `firstOrCreate`, so repeated runs won't duplicate the record, but it will create the account on a clean production database.

**Fix:** Either gate `UsersSeeder` to non-production environments:

```php
if (app()->environment(['local', 'testing', 'staging'])) {
    $this->call([UsersSeeder::class]);
}
```

Or remove hardcoded credentials entirely and use environment variables or interactive prompts for the initial admin user.

---

### MED-09 — Categories Can Have Parents From a Different Group (No Cross-Group Guard)

**File:** `database/migrations/2026_04_18_000016_create_categories_table.php:11-19`

```php
$table->foreignId('group_id')->constrained('category_groups')->cascadeOnDelete();
$table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
```

The `parent_id` FK points to the `categories` table with no constraint ensuring the parent belongs to the same `group_id`. A "Technology" category from the `topics` group could be assigned as parent to a "Phones" category from the `product-categories` group. The `wouldCreateCycle()` check in `CategoryService` does not check group membership either.

**Fix:** Add a check constraint or enforce at the application level in `CategoryService::move()` and `CategoryService::create()`:

```php
if ($parentId !== null) {
    $parentGroup = Category::where('id', $parentId)->value('group_id');
    if ($parentGroup !== $groupId) {
        throw new \InvalidArgumentException("Parent category must belong to the same group.");
    }
}
```

---

## LOW SEVERITY ISSUES

Cosmetic, structural, or minor concerns that won't cause failures in current scope but should be addressed before the project matures.

---

### LOW-01 — `UserSchema::resolved()` In-Process Static Cache Can Return Stale Data in Long-Lived Processes

**File:** `app/Models/UserSchema.php:17-37`

```php
private static ?self $resolved = null;

public static function resolved(): static
{
    if (static::$resolved === null) {
        static::$resolved = static::with(...)->firstOrCreate(['id' => 1]);
    }
    return static::$resolved;
}
```

In standard PHP-FPM, each request gets a fresh process so `$resolved` resets per request — safe. In Laravel Octane (Swoole/RoadRunner), the process persists across requests. If `UserSchema` is updated (field layout changed via admin), `static::$resolved` holds the old object for all subsequent requests in that worker until the worker restarts.

The `flushResolved()` method exists but is not called after admin updates.

**Fix:** Call `UserSchema::flushResolved()` in the admin controller after any update to the user schema or its related field layout. Also consider whether this cache is necessary at all for the current traffic expectations.

---

### LOW-02 — Permission Gaps for Entry and Field Management Areas

**File:** `database/seeders/RolesPermissionsSeeder.php`

The permission system defines permissions for:
- `api`, `access admin`, user management, user tokens, category groups, categories, media libraries

There are **no defined permissions** for:
- Entry management (create/edit/delete entries, entry groups, entry types)
- Field management (fields, field groups, field layouts)
- Status group management
- Media management (uploading/editing individual media items)

All of these admin sections currently rely on the **super admin gate bypass** to be accessible (non-super-admins cannot reach them). This means the admin role cannot delegate entry editing or field configuration to non-super-admin users. The RBAC model is incomplete for the content management use cases the system is designed for.

**Fix:** Add permissions for each major content area to `RolesPermissionsSeeder` and assign them appropriately to the `admin` role.

---

### LOW-03 — `Entry::handle` Auto-Generation Can Produce an Empty String

**File:** `app/Repositories/EntryRepository.php:126`

```php
$entry->handle = $data['handle'] ?? Str::slug($entry->title ?? '');
```

If no explicit handle is provided and the entry title evaluates to empty/null (theoretically prevented by the `NOT NULL` column, but possible through direct model assignment), `Str::slug('')` returns `''`. An empty string handle fails the unique constraint `entry_group_handle_unique` in a non-obvious way (MySQL's behavior with empty strings in unique indexes).

**Fix:** Add a guard after handle generation:

```php
$handle = $data['handle'] ?? Str::slug($entry->title ?? '');
if (empty($handle)) {
    throw new \InvalidArgumentException('Entry handle cannot be empty.');
}
$entry->handle = $handle;
```

---

### LOW-04 — Concurrent Entry Creation With Same Title Will Race on `handle` Unique Constraint

**File:** `app/Repositories/EntryRepository.php:126`

The `upsertFieldValue()` method handles the race condition for field values via a try/catch on `SQLSTATE 23000`. No equivalent handling exists for the `entry_group_handle_unique` constraint on the `entries` table. Two simultaneous requests to create an entry with title "My Post" in the same group will both compute `handle = 'my-post'`, and one will receive an unhandled `QueryException` from the DB.

**Fix:** Add suffix disambiguation (append a counter) when a handle collision is detected, similar to how WordPress and Craft CMS handle duplicate slugs. Or add a DB-level lock on the entry group row before generating the handle.

---

---

## BUSINESS RULES ISSUES

Issues where the logic, even if technically correct, could cause unexpected behavior or content management problems in real-world use.

---

### [RESOLVED] BR-01 — Dual Publication State: `status` String + `published_at` Timestamp Are Not Coordinated

The system has two independent signals for whether an entry is "publicly visible":

1. **`status` field** (`'draft'`, `'published'`, `'archived'`) — a string handle stored on the entry
2. **`published_at` timestamp** — nullable; the `scopePublished` scope checks `published_at IS NOT NULL AND published_at <= now()`

These are entirely independent. There is no code that enforces consistency between them. The resulting behaviors are:

| status | published_at | `scopePublished` returns? | Expected behavior? |
|---|---|---|---|
| `draft` | past date | **YES** | No — should be excluded |
| `published` | null | No | Potentially yes — depends on design intent |
| `published` | future date | No | Correct — scheduled publish |
| `archived` | past date | **YES** | No — should be excluded |

Front-end templates using `scopePublished()` will display draft and archived content if `published_at` has been set, regardless of editorial status.

**Recommendation:** Define which system is authoritative. Two approaches:

**Option A — Status is canonical.** Modify `scopePublished` to also require `status = 'published'`:
```php
public function scopePublished(Builder $query): Builder
{
    return $query->where('status', 'published')
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now());
}
```

**Option B — Eliminate `published_at` as a separate concept.** Use only `status` for editorial state and `published_at` purely as metadata (display date). Remove `published_at` from the `scopePublished` check.

Either way, the behavior must be clearly defined and enforced consistently.

---

### BR-02 — Entry Relationship Graph Allows Indirect Cycles

**File:** `app/Repositories/EntryRepository.php:257-260`

```php
// Only prevents A → A. Indirect cycles (A → B → A) are
// intentionally not enforced here...
$relatedIds = array_values(array_filter(
    $relatedIds,
    fn($id) => (int)$id !== $entry->getKey()
));
```

The comment explicitly acknowledges that cycles like `A → B → A` are permitted. Any consumer of `entryRelationships` that performs recursive traversal (e.g., "related entries of related entries") will enter an infinite loop unless the caller implements depth-limiting or visited-set logic.

If the system ever renders related entries recursively in Twig templates, this will cause infinite recursion and stack overflow errors.

**Recommendation:** Document the maximum traversal depth in the `Entry::entryRelationships` relationship docblock. Add a depth limit to any recursive entry-relationship resolution. Consider adding a cycle check at the repository level if the UX/admin does not clearly convey to editors that cycles are allowed.

---

### BR-03 — Category Hierarchy Allows Parent From Different Group (See Also MED-09)

As noted in MED-09, categories can be parented to categories in a different group. This means category trees can become cross-contaminated. A "Technology" category (topics group) becoming a parent of "Phones" (product-categories group) would make the product hierarchy incorrect. The `CategoryService::tree()` method queries by group but follows `childrenRecursive` which does not filter by group:

```php
// Category::childrenRecursive() does not scope by group_id
public function childrenRecursive(int $maxDepth = 10): HasMany
{
    return $this->children()->with([
        'childrenRecursive' => fn ($q) => $q->childrenRecursive($maxDepth - 1),
    ]);
}
```

A cross-group parent would not appear in the group's `tree()` results, but the orphaned category would still have a `parent_id` pointing outside its group.

---

### BR-04 — Entry Tree `nullOnDelete` on `parent_id` Creates Silent URI Orphans

**File:** `database/migrations/2026_04_23_200641_create_entry_tree_table.php:23-25`

```php
$table->foreignId('parent_id')
    ->nullable()
    ->constrained('entry_trees')
    ->nullOnDelete();   // parent deleted → child becomes root with old URI
```

When a parent `EntryTree` node is deleted, all children have their `parent_id` set to `null`. The children become roots but retain their URIs (e.g., `/blog/2025/my-post` is now a root node instead of a child of `/blog/2025`). The URI is now structurally disconnected from the actual tree and may conflict with a new root-level entry.

The `uri` column has a globally unique constraint, so there is no data corruption. However:
1. Orphaned subtrees exist in the tree with incorrect depth values
2. The site routing will still serve requests to the old URIs (which may or may not be desired)
3. No cleanup cascade exists to remove or re-root the child URI hierarchy

**Recommendation:** Add an observer or cascading delete on `EntryTree` that either deletes the entire subtree or re-parents children to the deleted node's parent when the node is removed.

---

### BR-05 — Super Admin Gate Bypass Has No Audit Trail

**File:** `app/Providers/AppServiceProvider.php:84-86`

```php
Gate::before(function ($user, $ability) {
    return $user->hasRole('super admin') ? true : null;
});
```

Super admins bypass all policy checks and authorization gates. There is no logging, audit trail, or rate limiting specific to super admin actions. Any user who has been assigned the `super admin` role can perform any operation in the system with no record of what they did beyond Laravel's default logging.

**Recommendation:** Add an event listener or middleware that logs gate bypass events for super admin users to a separate audit table.

---

### BR-06 — `apilog` Captures Full Response Bodies for All JSON Responses

**File:** `app/Http/Middleware/LogRequestResponse.php:131-133`

```php
if ($response instanceof JsonResponse) {
    $payload['body'] = $this->sanitizeValue($response->getData(true));
}
```

For JSON API responses (all normal API calls), the full response body is logged. While the middleware sanitizes known sensitive keys, any business-specific sensitive data in the response (user PII, private entry content) will be stored in `api_logs`. The truncation at 4000 characters helps bound the size per record, but the data is still stored in plaintext.

**Recommendation:** Review what data should be exempt from logging. Consider logging only status codes and route paths for successful responses, and full payloads only for error responses.

---

### BR-07 — Entry Tree `depth` Column Is Not Automatically Maintained

**File:** `app/Models/EntryTree.php`

The `depth` column on `entry_trees` is a stored integer. There is no observable code that recalculates `depth` when a tree node is moved (parent changed) or when parent nodes are deleted and children are re-rooted. If depth values become stale, any logic that uses `depth` for breadcrumb generation, `max_depth` enforcement, or UI rendering will produce incorrect results.

`EntryType.max_depth` (the maximum allowed nesting depth) is validated against this `depth` value. If depth is wrong, entries deeper than `max_depth` could be created silently.

**Recommendation:** Ensure that any operation which modifies `parent_id` on an `EntryTree` record also recalculates `depth` for the node and all its descendants.

---

*End of Phase 1 Issues Report*
