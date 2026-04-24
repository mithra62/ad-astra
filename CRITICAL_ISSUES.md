# Critical Issues & Fix Recommendations

## Overview

This document combines the full fragility analysis of the data model with concrete fix recommendations for each issue. Issues are grouped by severity. Each entry describes what is fragile, what scenario triggers it, what the failure mode is, and exactly what to change to fix it.

---

## Critical — Will Cause Production Failures

---

### 1. [RESOLVED] `Entry::update()` and `Entry::delete()` bypass Eloquent entirely

**What is fragile:**
Both methods are overridden in `app/Models/Entry.php` to route through `EntryRepository`. Eloquent's observer events (`updating`, `updated`, `deleting`, `deleted`) never fire. Any feature that hooks into those events — audit logs, cache invalidation, search indexing, webhooks — silently does nothing. `$model->isDirty()` also stops working correctly. This is invisible because the code appears to work until an observer is added and never triggers.

**Failure mode:** Observer-based infrastructure silently inert. Hard to diagnose because no error is raised.

**Fix:** Remove both method overrides from `app/Models/Entry.php`:

```php
// DELETE these two methods from Entry.php entirely:
//   public function update(array $data = [], array $options = []): static { ... }
//   public function delete(): bool { ... }
```

Any call site using `$entry->update([...])` must be updated to go through the service explicitly:

```php
// Before:
$entry->update(['title' => 'New Title', 'fields' => [...]]);

// After:
\App\Facades\Entries::update($entry, ['title' => 'New Title', 'fields' => [...]]);
```

Check `app/Http/Controllers/Admin/` and `app/Actions/Entry/` for all `$entry->update()` and `$entry->delete()` calls and replace them.

---

### 2. [RESOLVED] `EntryType::class` and `FieldType::object` stored as plain strings

**What is fragile:**
Both `entry_types.class` and `field_types.object` store fully-qualified class names as plain VARCHAR with no validation. Renaming `PodcastEpisodeEntryType` to `PodcastEpisode`, or moving the namespace, leaves every row pointing at a class that no longer exists. `EntryTypeRegistry::instantiate()` throws a `RuntimeException` at request time. Entries in that group become completely unloadable and users see 500 errors. There is no compile-time or deploy-time signal.

**Failure mode:** Hard runtime exception when loading any entry of the affected type. Silent until the class is gone.

**Fix — validate before storing** in `app/Actions/Entry/Type/CreateNewEntryType.php` and `EditEntryType.php`:

```php
if (! class_exists($data['class'])) {
    throw new \InvalidArgumentException("Class [{$data['class']}] does not exist.");
}
if (! is_subclass_of($data['class'], \App\EntryTypes\AbstractEntryType::class)) {
    throw new \InvalidArgumentException("Class [{$data['class']}] must extend AbstractEntryType.");
}
```

Apply the same check wherever `field_types.object` is stored.

**Fix — add an Artisan health-check command** (`app/Console/Commands/ValidateClassReferences.php`) and run it in CI:

```php
EntryType::all()->each(function ($type) {
    if (! class_exists($type->class)) {
        $this->error("EntryType [{$type->handle}] references missing class [{$type->class}]");
    }
});
FieldType::all()->each(function ($type) {
    if (! class_exists($type->object)) {
        $this->error("FieldType [{$type->name}] references missing class [{$type->object}]");
    }
});
```

---

### 3. [RESOLVED] Polymorphic type columns use raw class name strings with no morph map

**What is fragile:**
`field_values.fieldable_type` and other polymorphic columns store raw class names like `App\Models\Entry`. Renaming or moving the class makes every row silently an orphan — queries return empty collections rather than erroring. Additionally, `EntryRepository::applyFieldValues()` hardcodes `Entry::class` while `PersistsFieldValues::setField()` uses `$model->getMorphClass()`. If a morph alias is ever configured, these two paths diverge and produce duplicate FieldValue rows — one visible, one not.

**Failure mode:** Silent data loss. Field values remain in the database but are invisible to the ORM. No FK constraint surfaces the break.

**Fix — register a morph map** in `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::morphMap([
        'entry'    => \App\Models\Entry::class,
        'category' => \App\Models\Category::class,
        'user'     => \App\Models\User::class,
    ]);
    // ... existing boot code
}
```

**Fix — backfill existing rows** in a new migration (run once):

```php
DB::table('field_values')
    ->where('fieldable_type', 'App\Models\Entry')->update(['fieldable_type' => 'entry']);
DB::table('field_values')
    ->where('fieldable_type', 'App\Models\Category')->update(['fieldable_type' => 'category']);
DB::table('field_values')
    ->where('fieldable_type', 'App\Models\User')->update(['fieldable_type' => 'user']);
// Repeat for any other polymorphic tables (entry_categories, etc.)
```

---

## High — Silent Data Loss or Hard Failures Under Real Conditions

---

### 4. `status` column has no referential integrity

**What is fragile:**
`entries.status` is a plain VARCHAR. Typos (`'publishd'`), programmatic mutations, and direct DB updates all succeed silently. `Entry::withStatus('published')` returns nothing with no error after a rename or typo.

**Failure mode:** Silent filtering failures. Entries appear in the database but drop out of every status-scoped query.

**Fix — validate in `StoreEntryRequest`** and a new `UpdateEntryRequest`:

```php
'status' => [
    'nullable',
    'string',
    \Illuminate\Validation\Rule::exists('statuses', 'handle')->where(function ($query) {
        $typeHandle = $this->input('type_handle');
        $entryType  = \App\Models\EntryType::where('handle', $typeHandle)
            ->with('entryGroup.statusGroup')
            ->first();
        $groupId = $entryType?->entryGroup?->statusGroup?->getKey();
        if ($groupId) {
            $query->where('status_group_id', $groupId);
        }
    }),
],
```

---

### 5. [RESOLVED] `PodcastEpisodeEntryType` has a race condition on episode numbers

**What is fragile:**
`beforeCreate` reads `Entry::where('entry_group_id', $groupId)->count()` then adds 1. Two concurrent creates see the same count and assign the same episode number. No lock and no unique constraint on the episode number field value.

**Failure mode:** Duplicate episode numbers silently assigned. Breaks sort ordering and expected sequence.

**Fix** in `app/EntryTypes/PodcastEpisodeEntryType.php`:

```php
public function beforeCreate(array $data): array
{
    if (! isset($data['fields']['episode_number'])) {
        $groupId = $this->getRecord()->entry_group_id;

        $next = \DB::transaction(function () use ($groupId) {
            \App\Models\EntryGroup::where('id', $groupId)->lockForUpdate()->first();
            return \App\Models\Entry::where('entry_group_id', $groupId)->count() + 1;
        });

        $data['fields']['episode_number'] = $next;
    }

    if (empty($data['published_at'])) {
        $data['published_at'] = now();
    }

    return $data;
}
```

---

### 6. `status_group_id` on `entry_groups` is nullable — entries can have `NULL` status

**What is fragile:**
`EntryRepository::applyStatus()` silently skips setting a default status when no status group is configured. Entries are created with `status = NULL` and are excluded from every status scope with no error.

**Failure mode:** Silent omission. Entries exist but are invisible to all filtered queries.

**Fix — require status group in `StoreEntryGroupRequest`:**

```php
'status_group_id' => ['required', 'integer', 'exists:status_groups,id'],
```

**Fix — throw instead of silently skipping** in `app/Repositories/EntryRepository.php`:

```php
private function applyStatus(Entry $entry, ?string $handle, bool $applyDefault): void
{
    if ($handle) {
        $entry->status = $handle;
        return;
    }

    if ($applyDefault) {
        $statusGroup = $entry->entryGroup?->statusGroup;
        $default     = $statusGroup?->statuses->firstWhere('is_default', true);

        if (! $default) {
            throw new \RuntimeException(
                "EntryGroup [{$entry->entryGroup?->handle}] has no default status configured."
            );
        }

        $entry->status = $default->handle;
    }
}
```

---

### 7. [RESOLVED] Lifecycle hooks mutate `$data` by reference with no rollback on exception

**What is fragile:**
`beforeCreate(array &$data)` and `beforeUpdate(Entry $entry, array &$data)` mutate the caller's array in place. If a hook throws mid-mutation, the caller receives partially-modified data. On retry, the second attempt starts from the already-modified state — the podcast episode number is pre-injected, fields may already be normalized.

**Failure mode:** Corrupted input on retry. Second attempt processes already-mutated data.

**Fix — change hook signatures to return the array** in `app/EntryTypes/AbstractEntryType.php`:

```php
public function beforeCreate(array $data): array { return $data; }
public function beforeUpdate(Entry $entry, array $data): array { return $data; }
```

**Fix — update `EntryRepository`** to assign the return value:

```php
// create():
$data = $entryType->beforeCreate($data);

// applyData():
$data = $typeObject->beforeUpdate($entry, $data);
```

**Fix — update all concrete entry types** to return `$data`:

```php
// EventEntryType, NewsArticleEntryType, JobListingEntryType, PodcastEpisodeEntryType
public function beforeCreate(array $data): array
{
    // ... mutations ...
    return $data;
}
```

---

### 8. Multiple `is_default = true` statuses per group are not constrained

**What is fragile:**
No unique constraint prevents two statuses in the same group having `is_default = true`. `$statusGroup->statuses->firstWhere('is_default', true)` returns the first match, which is non-deterministic after reordering.

**Failure mode:** Unpredictable default status for new entries. Silently inconsistent.

**Fix — enforce it in the StatusService or equivalent action** (before setting a new default):

```php
Status::where('status_group_id', $groupId)
    ->where('is_default', true)
    ->update(['is_default' => false]);
// Then set the new default.
```

**Fix — add a unique partial index** in a new migration (PostgreSQL):

```php
DB::statement(
    'CREATE UNIQUE INDEX statuses_group_default_unique ON statuses (status_group_id) WHERE is_default = true'
);
```

---

### 9. [RESOLVED] Changing a field's type silently corrupts existing `field_values` rows

**What is fragile:**
`FieldValue` stores values in five typed columns (`value_text`, `value_integer`, etc.). `storageColumn()` determines which to read based on the current field type. Changing a field from text to integer leaves data in `value_text` but code reads `value_integer`, returning `NULL` silently. The old value is still in the row, just invisible.

**Failure mode:** Silent data loss. Existing values become invisible with no migration path.

**Fix — block type changes when values exist** in `app/Actions/Field/EditField.php`:

```php
if ($field->isDirty('field_type_id') && $field->fieldValues()->exists()) {
    throw new \RuntimeException(
        "Cannot change the type of field [{$field->slug}] — it has existing values. Migrate or clear the values first."
    );
}
```

---

## Medium — Wrong Behavior Under Specific Conditions

---

### 10. [RESOLVED] `resolveLayoutFields()` merge order is wrong — group-level fields win on collision

**What is fragile:**
`$groupFields->merge($typeFields)->unique('id')` keeps the first occurrence per ID. Since `merge()` appends `$typeFields` after `$groupFields`, and `unique()` keeps the first seen, group-level fields silently override type-level fields on conflict. The documented intent is the opposite ("type-specific on top, group-shared below").

**Failure mode:** Type-level field layout is silently ignored when the same field exists in both layouts.

**Fix** in `app/Repositories/EntryRepository.php`:

```php
private function resolveLayoutFields(Entry $entry): \Illuminate\Support\Collection
{
    $entry->loadMissing([
        'entryGroup.fieldLayout.tabs.elements.field.fieldType',
        'entryType.fieldLayout.tabs.elements.field.fieldType',
    ]);

    $groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
    $typeFields  = $entry->entryType->fieldLayout?->fields() ?? collect();

    // Type-level fields take precedence: start with type fields, then fill
    // in group fields that don't share an ID with any type field.
    return $typeFields->merge($groupFields)->unique('id');
}
```

---

### 11. Entry slug uniqueness is group-scoped — cross-group slug lookups are ambiguous

**What is fragile:**
`UNIQUE(entry_group_id, slug)` allows the same slug in multiple groups. Any lookup by slug alone — URL routing, API endpoints, SEO redirects — returns an arbitrary result when the same slug exists in more than one group.

**Failure mode:** Wrong entry returned. No error, just silent misrouting.

**Fix:** Ensure all slug-based lookups always scope by group:

```php
// Always:
Entry::inGroup($groupHandle)->where('slug', $slug)->firstOrFail();

// Never:
Entry::where('slug', $slug)->firstOrFail();
```

Add a helper to `EntryService` if cross-group slug lookup is needed:

```php
public function findBySlug(string $slug, string|int|\App\Models\EntryGroup $group): ?Entry
{
    return $this->repository->findBySlug($slug, $group);
}
```

---

### 12. [RESOLVED] Category parent cycles are not prevented

**What is fragile:**
`categories.parent_id` has `nullOnDelete` but no constraint prevents A → B → A. `Category::childrenRecursive()` eager-loads without depth limits and will loop until PHP memory is exhausted.

**Failure mode:** Infinite recursion. PHP memory exhaustion. No guard at DB or application level.

**Fix — add cycle detection in `CategoryService::move()`:**

```php
public function move(Category $category, ?int $parentId, int $sortOrder = 0): Category
{
    if ($parentId && $this->wouldCreateCycle($category, $parentId)) {
        throw new \InvalidArgumentException('Moving this category would create a circular reference.');
    }

    $category->update(['parent_id' => $parentId, 'sort_order' => $sortOrder]);
    return $category->refresh();
}

private function wouldCreateCycle(Category $category, int $targetParentId): bool
{
    $candidate = \App\Models\Category::find($targetParentId);
    while ($candidate) {
        if ($candidate->id === $category->id) {
            return true;
        }
        $candidate = $candidate->parent_id
            ? \App\Models\Category::find($candidate->parent_id)
            : null;
    }
    return false;
}
```

**Fix — add a depth guard** to `childrenRecursive()` in `app/Models/Category.php`:

```php
public function childrenRecursive(int $maxDepth = 10): HasMany
{
    if ($maxDepth <= 0) {
        return $this->hasMany(static::class, 'parent_id')->whereRaw('0=1');
    }
    return $this->hasMany(static::class, 'parent_id')
        ->with(['childrenRecursive' => fn($q) => $q->childrenRecursive($maxDepth - 1)]);
}
```

---

### 13. `required` flag on `TabElement` is display-only — not enforced server-side

**What is fragile:**
`field_layout_tab_elements.required` is stored and shown in the UI but never read by `StoreEntryRequest` or `EntryRepository`. An entry can be saved with an empty value for a required field with no validation error.

**Failure mode:** Silent acceptance of incomplete entries. Stakeholders see "required" in the UI but it has no effect.

**Fix:** Read the required fields from the layout in `StoreEntryRequest::rules()`:

```php
public function rules(): array
{
    $rules = [...]; // base rules

    $typeHandle = $this->input('type_handle');
    $entryType  = \App\Models\EntryType::where('handle', $typeHandle)
        ->with('fieldLayout.tabs.elements', 'entryGroup.fieldLayout.tabs.elements')
        ->first();

    if ($entryType) {
        $allElements = collect();
        foreach ([$entryType->fieldLayout, $entryType->entryGroup?->fieldLayout] as $layout) {
            if ($layout) {
                $layout->tabs->each(fn($tab) => $allElements = $allElements->merge($tab->elements));
            }
        }

        foreach ($allElements->where('required', true) as $element) {
            $slug = $element->field->slug;
            $rules["fields.{$slug}"] = ['required'];
        }
    }

    return $rules;
}
```

---

### 14. `FieldLayout.name` is nullable in the schema but assumed present everywhere

**What is fragile:**
The migration defines `name` as `nullable()` but the breadcrumbs, sidebar, and lists render `layout.name` unconditionally. A nameless layout created by direct DB insert or a broken seeder silently breaks those views.

**Failure mode:** UI crash or blank layout label in navigation and breadcrumbs.

**Fix — add a NOT NULL constraint** in a new migration:

```php
Schema::table('field_layouts', function (Blueprint $table) {
    $table->string('name')->nullable(false)->change();
});
```

Alternatively, add a database default of `'Unnamed Layout'` and tighten the migration so future layouts require a name.

---

### 15. Entry relationship cycles are only prevented at depth 1

**What is fragile:**
`syncRelationshipField()` filters `$id !== $entry->getKey()` to prevent direct self-reference (A → A), but does not check indirect cycles (A → B → A). If code recursively loads `relatedEntry.relatedEntry`, it loops.

**Failure mode:** Infinite loop or stack overflow in recursive related-entry traversal.

**Fix:** Document the constraint explicitly and add a depth limiter to any recursive related-entry loading:

```php
// If you build a recursive related-entry loader:
public function loadRelatedRecursive(Entry $entry, int $maxDepth = 3, array $seen = []): Collection
{
    if ($maxDepth <= 0 || in_array($entry->id, $seen, true)) {
        return collect();
    }
    $seen[] = $entry->id;
    // load related entries...
}
```

---

### 16. `FieldValue` unique constraint fires as a raw `QueryException`

**What is fragile:**
There is no application-level guard before inserting a field value. A race condition between two concurrent requests can attempt two inserts before either sees the constraint. The result is an unhandled `QueryException`.

**Failure mode:** Unhandled database exception surfaces to the user instead of a validation message.

**Fix:** The repository already uses `updateOrCreate`, which mitigates most cases. The remaining risk is direct inserts bypassing the repository. Add a comment/guard at any raw insert site, and catch the `QueryException` at the controller level if needed:

```php
try {
    // repository call
} catch (\Illuminate\Database\QueryException $e) {
    if ($e->getCode() === '23000') { // unique constraint violation
        // retry or return a validation error
    }
    throw $e;
}
```

---

## Low — Performance or Edge-Case Correctness

---

### 17. [RESOLVED] `EntryTypeRegistry` queries the database on every resolution with no caching

**What is fragile:**
`resolveByHandle()` runs a query with eager-loads on every call. In a request that processes multiple entries of the same type, this fires repeatedly for the same record.

**Fix** in `app/EntryTypes/EntryTypeRegistry.php`:

```php
private array $cache = [];

public function resolveByHandle(string $handle): AbstractEntryType
{
    if (! isset($this->cache[$handle])) {
        $record = EntryTypeRecord::where('handle', $handle)
            ->with(['entryGroup', 'fieldLayout.tabs.elements.field.fieldType'])
            ->firstOrFail();

        $this->cache[$handle] = $this->instantiate($record);
    }

    return $this->cache[$handle];
}
```

---

### 18. `Entry::field(handle)` is N+1-prone outside the standard controller path

**What is fragile:**
`fieldValues.field.fieldType` must be eager-loaded before calling `field()`. Controllers do this correctly, but console commands, queue jobs, or API routes that call `field()` without the full eager-load chain get one query per field per entry.

**Fix:** Add an explicit check or PHPDoc to make the requirement impossible to miss:

```php
// app/Models/Entry.php
/**
 * Resolve a field value by handle.
 *
 * REQUIRES: fieldValues.field.fieldType and entryRelationships.field.relatedEntry
 * to be eager-loaded. Call Entry::with(EntryRepository::defaultEagerLoad()) first.
 */
public function field(string $handle): mixed { ... }
```

---

### 19. `UserSchema` singleton leaks across tests

**What is fragile:**
`UserSchema::instance()` uses a static property for request-level caching. Static state persists across PHPUnit test cases in the same process, leaving dirty schema state between tests.

**Fix — add a `reset()` method** to the UserSchema class and call it in your test base:

```php
// UserSchema class:
public static function reset(): void
{
    static::$instance = null;
}

// tests/TestCase.php:
protected function setUp(): void
{
    parent::setUp();
    \App\Schema\UserSchema::reset();
}
```

---

### 20. `defaultEagerLoad()` always loads all relations regardless of query needs

**What is fragile:**
`findOrFail()` and `find()` always load authors, categories, fieldValues, entryRelationships, and their nested relations — even for a query that only needs the entry title.

**Fix:** Add a lightweight variant for read-only metadata access:

```php
// app/Repositories/EntryRepository.php
public function findMeta(int $id): ?Entry
{
    return Entry::with(['entryGroup', 'entryType', 'creator'])->find($id);
}
```

Use `findMeta()` in list views and dashboards where full field resolution is not needed.

---

## Summary Table

| # | Issue | Severity | File(s) to change | Effort |
|---|---|---|---|---|
| 1 | Entry::update/delete bypass observers | Critical | `Entry.php`, call sites | Small |
| 2 | Class strings for EntryType/FieldType | Critical | Actions, new Artisan command | Small |
| 3 | Polymorphic type with no morph map | Critical | `AppServiceProvider`, new migration | Small |
| 4 | Status string with no FK | High | `StoreEntryRequest`, new `UpdateEntryRequest` | Small |
| 5 | Podcast episode race condition | High | `PodcastEpisodeEntryType` | Small |
| 6 | Nullable status group → NULL status | High | `StoreEntryGroupRequest`, `EntryRepository` | Small |
| 7 | Hook mutates $data by reference | High | `AbstractEntryType`, `EntryRepository`, 4 entry types | Small |
| 8 | Multiple default statuses unconstrained | High | StatusService/action, new migration | Small |
| 9 | Field type change corrupts field_values | High | `EditField` action | Small |
| 10 | resolveLayoutFields merge order wrong | Medium | `EntryRepository` | Trivial |
| 11 | Cross-group slug lookup ambiguous | Medium | All slug lookup call sites | Small |
| 12 | Category cycles cause infinite recursion | Medium | `CategoryService`, `Category.php` | Small |
| 13 | required flag not enforced server-side | Medium | `StoreEntryRequest` | Medium |
| 14 | FieldLayout.name nullable in schema | Medium | New migration | Trivial |
| 15 | Entry relationship cycles at depth > 1 | Medium | Any recursive loader | Small |
| 16 | FieldValue unique constraint → QueryException | Medium | Controller error handling | Small |
| 17 | EntryTypeRegistry has no caching | Low | `EntryTypeRegistry` | Trivial |
| 18 | Entry::field() N+1 outside controllers | Low | PHPDoc on `Entry.php` | Trivial |
| 19 | UserSchema singleton leaks in tests | Low | `UserSchema`, `TestCase` | Trivial |
| 20 | defaultEagerLoad() loads everything always | Low | `EntryRepository` | Small |

**Highest leverage single change:** Issue 3 (morph map + backfill migration) closes the entire class of "model rename silently corrupts all field values" risk across Entry, Category, and User simultaneously, for about 30 lines of code.
