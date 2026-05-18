# Search Layer — Implementation Plan

## 1. Overview

This document describes the design and phased delivery of a full-text search subsystem for this Laravel application. It is written from a clean read of the existing codebase — the Field, FieldLayout, FieldValue, EntryGroup, EntryType, Entry, EntryRelationship, and User models, the `Fieldable` trait, the `EntryRepository`, and the field-type registry pattern in `App\Field\AbstractField` + `App\Field\Types\*` — and from the project's own conventions for naming, nesting, and trait composition.

The system supports search over Entries (configured at the Entry Group level) and Users out of the gate, and is structured so that adding a new searchable model type later (Categories, Media, future content types) is as cheap as adding a new field type today: subclass an abstract base, register a row, apply a trait. The goal is for the Search layer to feel like a sibling of the Field layer, with the same extensibility properties and the same conventions.

Code names introduced by this plan are prefixed with `Search` unless the name is already unique enough on its own (a model called `Search\Document` doesn't need a redundant `SearchSearchDocument`; a trait method called `searchEnqueue` does). Where a method on a host model could plausibly collide with anything else — `reindex`, `index`, `search`, `documents` — the prefix is applied.

## 2. Goals

- Full-text keyword search across **Entries** (configured per Entry Group) and **Users**.
- **Per-Field weight control**, configurable at four cascading levels: field-type default, field instance, Entry Group, and Search Collection.
- **Search Collections** that compose multiple Entry Groups (and optionally User-style searchable types) into a single named scope, with collection-specific priority overrides.
- **Extensibility** — adding a new searchable model type requires:
  1. One concrete subclass of `Search\AbstractSearchable`
  2. One row in `search_types`
  3. Applying the `Searchable` trait
  4. *No changes* to the indexer, query layer, observers, admin UI, or API.
- **Progressive delivery** — every phase ships a working slice that the next phase builds on. The work can stop after Phase 5 and you have keyword search; after Phase 7 and you have Collections; after Phase 8 and you have a public API.

## 3. Non-Goals (initial)

These are explicitly out of scope for the phases described here. Any of them can be added later without redesign.

- Faceted aggregation (counts per category, per status, etc.).
- Typo tolerance, synonyms, or language-specific analyzers — these are external-engine concerns.
- Auto-complete / suggest-as-you-type — separable feature, separate index shape.
- Search analytics dashboards.

## 4. Architectural Mirror — Field Layer ↔ Search Layer

The single most important design choice in this plan is to mirror the Field layer, not to invent a new pattern.

| Field Layer | Search Layer |
|---|---|
| `App\Field\AbstractField` | `App\Search\AbstractSearchable` |
| `App\Field\Types\Text`, `Textarea`, `Relationship`, ... | `App\Search\Types\EntrySearchable`, `UserSearchable`, ... |
| `App\Models\Field\Type` (DB registry, has `object` FQCN column) | `App\Models\Search\Type` (DB registry, same shape) |
| `App\Models\Field` (instance) | `App\Models\Search\Document` (instance per model+locale) |
| `App\Models\Field\Group` (grouping) | `App\Models\Search\Collection` (grouping) |
| `App\Traits\Fieldable` (consumer trait) | `App\Traits\Searchable` (consumer trait) |
| `field_values` table (polymorphic typed storage) | `search_documents` table (polymorphic FULLTEXT storage) |
| `App\Services\FieldService` | `App\Services\SearchService` |
| `Field\Type::instance()` resolves and constructs concrete | `Search\Type::instance()` resolves and constructs concrete |

Because the parallel is structural, every habit a developer already has working with fields transfers: how to register a new type, how to attach the layer to a host model, how the polymorphic storage works, how the service layer delegates to a typed instance.

## 5. Data Model

### 5.1 `search_types`

Mirrors `field_types` exactly. Registers concrete `AbstractSearchable` subclasses by class name.

```
search_types
─────────────────────────────
id
handle           string unique          'entry', 'user', ...
name             string
object           string                 FQCN of AbstractSearchable subclass
settings         json nullable
is_enabled       boolean default true
timestamps
```

### 5.2 `search_documents`

The polymorphic index. **One row per indexable content unit** — a Field's value, a synthetic title, a handle, a category name, a related Entry's title — not one row per searchable model. This decomposition is what makes per-Field weighting at the Collection level possible: weights are JOINed at query time, not baked into the document.

```
search_documents
─────────────────────────────
id
search_type_id            fk → search_types
searchable_id             unsignedBigInteger
searchable_type           string                  morph map alias
tenant_id                 unsignedBigInteger nullable    forward-compat per TenantModel.md
entry_group_id            unsignedBigInteger nullable    null for non-Entry rows
entry_type_id             unsignedBigInteger nullable
locale                    string(8)               'en', 'fr', ...

source_kind               enum('title','handle','field','category','related','tag','custom')
field_id                  unsignedBigInteger NOT NULL default 0     references fields.id; 0 sentinel for synthetic rows. NO DB-LEVEL FK — see note below.
source_id                 unsignedBigInteger NOT NULL default 0     references the source object's id (categories.id, entries.id, tags.id) per source_type; 0 sentinel for synthetic rows. NO DB-LEVEL FK — see note below.
source_type               string nullable          informational morph alias for source_id ('category','entry','tag', ...); used by observers to route cleanup
source_handle             string(64) nullable      diagnostic only — denormalized human-readable label for searchExplain()

search_content            mediumText              the text to be FULLTEXT-tokenized

status_handle             string nullable
is_public                 boolean default false
published_at              timestamp nullable
search_payload            json                    arbitrary searchable + facetable attrs

search_indexed_at         timestamp
timestamps

unique   (searchable_id, searchable_type, locale, source_kind, field_id, source_id)
index    (tenant_id, searchable_type, is_public, published_at)
index    (entry_group_id, locale)
index    (field_id)
index    (source_type, source_id)               supports reverse-lookups (e.g., "all rows pointing at this Entry")
fulltext (search_content)
```

This shape solves several problems at once:

- **Collection-specific weight overrides become a query-time JOIN**, not an index-time decision. Two Collections with different weights for the same Field on the same Entry just produce two different `ORDER BY` expressions over the same rows — neither one mutates the index.
- **Reverse-relationship and category staleness become localized**. When Entry B's title changes, only the `source_kind='related'` rows pointing at B (`source_id = B.id`) need updating, not the entire denormalized blob. When a Category is renamed, only the `source_kind='category'` rows with that `source_id` change. Each fix is one targeted UPDATE, not a full reindex of the affected Entries.
- **Configuration changes mostly stop triggering reindexes**. Per-Group or per-Collection weight changes are pure query-time concerns and require no document writes. Only changes that affect *content presence* — `is_searchable` toggling, a Field being added/removed from a layout, a category attach/detach — produce row inserts/deletes.

The unique key — `(searchable_id, searchable_type, locale, source_kind, field_id, source_id)` — uses **stable foreign-key ids, not handles**. This is deliberate: handles are mutable (a Field's handle can be edited; a Category's handle is unique only per `category_groups.id` per the existing `cat_group_handle_unique` constraint, not globally), so a unique key built on them would let collisions slip in. With ids, the key disambiguates correctly:

- Two Relationship fields (`field_id` differs) on the same Entry pointing at the same related Entry (`source_id` same) → two distinct rows.
- Two same-handle Categories from different category groups attached to the same Entry → distinct rows because `source_id` differs.
- Synthetic kinds (`title`, `handle`) → `field_id=0` and `source_id=0` (not NULL — MySQL's UNIQUE allows multiple NULLs and would silently let duplicate title rows in). The 0 sentinel makes the constraint enforce "exactly one title row per (searchable, locale)".

`source_type` and `source_handle` are kept for diagnostic and `searchExplain()` output but play no part in uniqueness.

### 5.2.0 Why no DB-level FK on `field_id` / `source_id`

`field_id` and `source_id` are **not** declared as MySQL foreign keys despite naming an id column from another table. The reason is that the same column carries a sentinel `0` value for synthetic rows where no source object exists (`source_kind='title'`, `source_kind='handle'`, etc.). A real FK constraint would reject those inserts because no `fields` row has id `0` and no `categories`/`entries`/`tags` row has id `0`.

This matches Laravel's existing convention for polymorphic columns — `field_values.fieldable_id`, `categorizables.categorizable_id`, and the morph `*_id` columns elsewhere in this codebase carry no DB-level FK either. Referential integrity is the application's job.

The Search layer maintains it through observers, declared in §9.1:

- `Field::deleting` → `DELETE FROM search_documents WHERE field_id = :id` (covers `source_kind='field'` rows AND `source_kind='related'` rows that used the deleted Relationship field).
- `Category::deleting` → `DELETE FROM search_documents WHERE source_type='category' AND source_id = :id`.
- `Tag::deleting` → `DELETE FROM search_documents WHERE source_type='tag' AND source_id = :id`.
- `Entry::deleting` → `DELETE FROM search_documents WHERE source_type='entry' AND source_id = :id` (purges `source_kind='related'` rows in *other* Entries that pointed at the one being deleted), in addition to deleting the Entry's own `searchable_id`-keyed rows.

These observer cleanups are part of Phase 4's deliverable. They are explicitly listed in the Phase 4 test cases. Implementers should not attempt to add DB-level FK constraints on these columns; the migration must omit them.

Alternative design that *could* preserve FKs — `field_id` and `source_id` declared `nullable` (with FKs) plus generated non-null columns `field_id_key BIGINT AS (COALESCE(field_id, 0)) STORED` and `source_id_key BIGINT AS (COALESCE(source_id, 0)) STORED` participating in the unique key — was considered and rejected. It doubles the column count, complicates inserts, and produces no operational benefit since cleanup paths still need to fire on related-Entry deletes (FK cascade only handles direct deletes, not the `source_kind='related'` rows that point to the deleted Entry from other Entries' index data).

### 5.2.1 Synthetic source_kinds

Some content units don't correspond to a Field. They get fixed `source_kind` values and their default weights live in `config/search.php` (overridable per-Group via JSON in `entry_group_search_configs`):

| `source_kind` | Produced by | Default weight | `field_id` | `source_id` | `source_type` |
|---|---|---|---|---|---|
| `title` | Entry/User core attribute | 2.0 | 0 | 0 | null |
| `handle` | Entry core attribute | 0.5 | 0 | 0 | null |
| `field` | a `FieldValue` (any non-relational type) | from cascade | the Field id | 0 | null |
| `category` | a Category attached to an Entry | 0.6 | 0 | `categories.id` | `'category'` |
| `related` | a related Entry's title (Relationship field) | 0.5 | the Relationship Field id | `entries.id` (the related Entry) | `'entry'` |
| `tag` | Spatie tag attached to a User | 0.7 | 0 | `tags.id` | `'tag'` |
| `custom` | extension hook | configurable | extension-defined | extension-defined | extension-defined |

The `source_kind` enum is the seam an extension uses to add new indexable concepts without touching the core indexer. New extensions choose stable id columns for `field_id` / `source_id` so the unique key continues to enforce one-row-per-content-unit.

### 5.3 `entry_group_search_configs`

Per-Group configuration. One row per `EntryGroup`, mirroring how a group has one `field_layout`.

```
entry_group_search_configs
─────────────────────────────
id
entry_group_id              fk unique cascadeOnDelete
search_is_indexable         boolean default true     master kill-switch
search_include_title        boolean default true
search_include_handle       boolean default false
search_include_categories   boolean default true
search_include_related      boolean default true     pull Relationship-field entry titles into meta
search_apply_freshness      boolean default false
search_freshness_half_life  unsignedSmallInt default 30
search_default_locale       string(8) default 'en'
search_status_multipliers   json nullable            override the global status multiplier map
timestamps
```

### 5.4 `entry_group_search_field_priorities`

Per-Group, per-Field weight. Read at query time only — no bucket column because per-Field rows replaced the bucket concept (each Field is its own row; weight is applied to the row's MATCH score directly).

```
entry_group_search_field_priorities
─────────────────────────────
id
entry_group_id      fk cascadeOnDelete
field_id            fk cascadeOnDelete
search_weight       decimal(4,2) default 1.00
timestamps
unique (entry_group_id, field_id)
```

A `search_weight = 0` row means "this Field's text is in the index for this Group, but contributes 0 to relevance." To exclude the Field's text from the index entirely, set `fields.is_searchable = false` (one source of truth: presence in the index follows the Field-instance / Field-type cascade, not the Group/Collection cascade).

### 5.5 `search_collections`

A named, composable search scope.

```
search_collections
─────────────────────────────
id
tenant_id        fk nullable                       null until tenancy lands (forward-compat per TenantModel.md)
name             string
handle           string
description      text nullable
locale           string(8) nullable                null = all locales
is_active        boolean default true
timestamps

unique ((COALESCE(tenant_id, 0)), handle)         functional unique index, MySQL 8.0.13+
```

The unique constraint cannot be a plain `unique (tenant_id, handle)`: MySQL's UNIQUE allows multiple rows with `tenant_id IS NULL`, which would silently let two pre-tenancy collections share the same handle. The functional unique index on `COALESCE(tenant_id, 0)` collapses NULL to a sentinel `0` for the purposes of uniqueness only, while leaving the column genuinely nullable for the FK semantics that arrive when tenancy lands. Equivalent on older MySQL: a `STORED` generated column (`tenant_scope_key BIGINT AS (COALESCE(tenant_id, 0)) STORED`) with `unique (tenant_scope_key, handle)`.

The same NULL-uniqueness trap applies anywhere a unique key includes a nullable column — `search_documents` is already protected by NOT NULL+sentinel on `field_id` and `source_id` (§5.2). Verify when adding any new unique constraint to a Search-layer table.

### 5.6 `search_collection_entry_groups`

Which Entry Groups a Collection includes, in a stated order.

```
search_collection_entry_groups
─────────────────────────────
id
search_collection_id   fk cascadeOnDelete
entry_group_id         fk cascadeOnDelete
sort_order             unsignedSmallInt default 0
timestamps
unique (search_collection_id, entry_group_id)
```

### 5.7 `search_collection_field_priorities`

Per-(Collection, Group, Field) override. **Keying by Group as well as Field is essential** — a Field is reusable across Groups, and a Collection that includes multiple Groups using the same Field needs the ability to set independent weights for each instance.

```
search_collection_field_priorities
─────────────────────────────
id
search_collection_id   fk cascadeOnDelete
entry_group_id         fk cascadeOnDelete
field_id               fk cascadeOnDelete
search_weight          decimal(4,2)
timestamps
unique (search_collection_id, entry_group_id, field_id)
```

These rows are read by the QueryBuilder when a search runs inside a Collection scope, JOINed against `search_documents.field_id`. Adding, removing, or changing a row here triggers no reindex — only the next query sees the new weight.

### 5.8 `search_collection_searchable_types`

Which searchable types (Entry, User, future) a Collection includes. Replaces the brittle `enum('entries','members','both')` antipattern with a registry-backed pivot.

```
search_collection_searchable_types
─────────────────────────────
id
search_collection_id   fk cascadeOnDelete
search_type_id         fk → search_types cascadeOnDelete
search_weight          decimal(4,2) default 1.00     inter-type ranking modifier
timestamps
unique (search_collection_id, search_type_id)
```

### 5.9 `fields` (extension)

Two columns added to allow per-instance overrides of field-type defaults.

```
fields (additions)
─────────────────────────────
+ is_searchable       boolean nullable        null = inherit from field type default
+ search_weight       decimal(4,2) nullable
```

### 5.10 `users` (extension)

User search is opt-in (see decision in §14). The opt-in lives on the `users` row itself, not on a separate config table — Users have no equivalent of EntryGroup, so the per-instance flag is the cleanest source of truth for `UserSearchable::searchShouldIndex()`.

```
users (additions)
─────────────────────────────
+ is_searchable       boolean default false   default off; admins flip on per-user or in bulk
+ search_weight       decimal(4,2) nullable    optional per-user override
```

A future "User Group" abstraction, if it lands, would gain its own `Search\Config` row analogous to `EntryGroup`'s — but until then, per-User opt-in is sufficient and avoids designing a config layer for a single-instance type.

## 6. Class Structure

### 6.1 `App\Search\AbstractSearchable`

The base class for "what does it mean to be searchable?" — one concrete subclass per searchable model type.

```php
namespace App\Search;

use App\Models\Search\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class AbstractSearchable
{
    protected string $handle = '';
    protected string $name = '';
    protected array $settings = [];
    protected ?Type $type = null;

    public function __construct(array $settings = [], ?Type $type = null);
    public function setType(Type $type): static;
    public function handle(): string;
    public function name(): string;

    /** Should the given model be indexed at all right now? Status, public flag, opt-in flag. */
    abstract public function searchShouldIndex(Model $model): bool;

    /** Locales this model should produce documents for. Default ['en']; translatable models override. */
    public function searchLocales(Model $model): array;

    /** The Field models whose values participate in indexing. Implementations resolve from layout. */
    abstract public function searchFieldsFor(Model $model): Collection;

    /**
     * Yield one row per content unit for (model, locale). Each row is an array shaped to search_documents
     * with at minimum: source_kind, field_id, source_id, source_type, source_handle, search_content.
     *
     * Default implementation calls searchTitleRows / searchHandleRows / searchFieldRows /
     * searchRelatedRows / searchCategoryRows / searchTagRows in order and yields from each.
     * Subclasses extend by overriding individual hook methods rather than the full producer.
     */
    public function searchToDocuments(Model $model, string $locale): iterable;

    // --- per-kind hook methods (override what applies to your model) -------------

    protected function searchTitleRows(Model $model, string $locale): iterable;
    protected function searchHandleRows(Model $model, string $locale): iterable;
    protected function searchFieldRows(Model $model, string $locale): iterable;
    protected function searchRelatedRows(Model $model, string $locale): iterable;
    protected function searchCategoryRows(Model $model, string $locale): iterable;
    protected function searchTagRows(Model $model, string $locale): iterable;
    protected function searchCustomRows(Model $model, string $locale): iterable;
}
```

The hook methods return empty by default; concrete subclasses override only the ones that apply. `EntrySearchable` overrides title/handle/field/related/category. `UserSearchable` overrides title/field/tag (no handle, no categories, no relational fields). A future `CategorySearchable` might override only title/field. This is the strategy pattern — the indexer iterates the abstract contract; subclasses contribute by extending.

Returning `iterable` (rather than `array`) lets implementations use `yield` for memory efficiency on Entries with many fields/relations, while remaining trivially testable with array-shaped fixtures.

### 6.2 `App\Search\Types\EntrySearchable`

```php
namespace App\Search\Types;

class EntrySearchable extends \App\Search\AbstractSearchable
{
    protected string $handle = 'entry';
    protected string $name = 'Entry';

    public function searchShouldIndex(Model $entry): bool;
    public function searchFieldsFor(Model $entry): Collection;       // delegates to EntryRepository::resolveLayoutFields

    protected function searchTitleRows(Model $entry, string $locale): iterable;     // one row, source_kind='title'
    protected function searchHandleRows(Model $entry, string $locale): iterable;    // one row if include_handle
    protected function searchFieldRows(Model $entry, string $locale): iterable;     // one row per searchable Field
    protected function searchRelatedRows(Model $entry, string $locale): iterable;   // one row per (Relationship field, related Entry)
    protected function searchCategoryRows(Model $entry, string $locale): iterable;  // one row per attached Category
}
```

`searchFieldsFor()` reuses the existing `EntryRepository::resolveLayoutFields()` so the same precedence (EntryType layout > EntryGroup layout) that drives field persistence drives indexing. `searchRelatedRows()` walks `Entry::field($handle)` for each Relationship-typed field and yields one row per (parent, Relationship field, related Entry) combination, with `field_id = relationshipFieldId`, `source_id = relatedEntry.id`, `source_type = 'entry'`. The reverse-Relationship trigger in §9.1 finds these rows by `(source_type='entry', source_id=B.id)` and refreshes their `search_content` when B's title changes — that's why `source_id`/`source_type` are stable foreign-key references rather than handle strings.

### 6.3 `App\Search\Types\UserSearchable`

```php
namespace App\Search\Types;

class UserSearchable extends \App\Search\AbstractSearchable
{
    protected string $handle = 'user';
    protected string $name = 'User';

    public function searchShouldIndex(Model $user): bool;            // gates on users.is_searchable
    public function searchFieldsFor(Model $user): Collection;        // pulls from Fieldable

    protected function searchTitleRows(Model $user, string $locale): iterable;      // 'name' in title; 'email' as a separate row
    protected function searchFieldRows(Model $user, string $locale): iterable;
    protected function searchTagRows(Model $user, string $locale): iterable;        // Spatie tags, source_id = tag.id, source_type = 'tag'
}
```

`UserSearchable` deliberately leaves `searchHandleRows`, `searchRelatedRows`, `searchCategoryRows` as the abstract base's empty defaults — Users have none of those concepts. Adding a future `CategorySearchable` is the same exercise: subclass, override the hooks that apply, leave the rest empty.

### 6.4 `App\Field\AbstractField` — extension

Each Field type declares its own indexability. This is the field-side counterpart of `AbstractSearchable` — one is "how is a model indexed", the other is "how does a field type contribute to that index".

```php
abstract class AbstractField
{
    // ...all existing methods unchanged...

    public function searchIsSearchable(): bool;             // default false
    public function searchDefaultWeight(): float;            // default 1.0
    public function searchToString(mixed $value): ?string;   // default null
}
```

Recommended per-type defaults:

| Type | Searchable | Default weight |
|---|---|---|
| `Text` | yes | 1.0 |
| `Textarea` | yes | 1.0 |
| `EmailAddress` | yes | 0.5 |
| `Url` | yes | 0.3 |
| `Telephone` | yes | 0.3 |
| `Relationship` | yes | 0.5 |
| `Number` | no | — |
| `Date` | no | — |
| `Boolean` | no | — |
| `ColorPicker` | no | — |

Non-searchable types are still queryable as facets via `search_payload` JSON; they just don't get FULLTEXT-tokenized. The "bucket" concept is dropped — it was a workaround for storing multiple weighted text streams in one row, and the per-content-unit document model in §5.2 makes it unnecessary.

### 6.5 `App\Models\Search\Type`

Mirrors `Field\Type`.

```php
namespace App\Models\Search;

class Type extends Model
{
    protected $table = 'search_types';
    protected $fillable = ['handle', 'name', 'object', 'settings', 'is_enabled'];
    protected $casts = ['settings' => 'array', 'is_enabled' => 'boolean'];

    public function searchableType(): string;          // morph map alias
    public function instance(): \App\Search\AbstractSearchable;
    public function documents(): HasMany;              // → Search\Document
}
```

### 6.6 `App\Models\Search\Document`

```php
namespace App\Models\Search;

class Document extends Model
{
    protected $table = 'search_documents';
    protected $casts = [
        'search_payload'    => 'array',
        'search_indexed_at' => 'datetime',
        'is_public'         => 'boolean',
        'published_at'      => 'datetime',
    ];

    public function searchable(): MorphTo;
    public function searchType(): BelongsTo;
    public function entryGroup(): BelongsTo;
    public function field(): BelongsTo;                            // nullable: only set for source_kind='field'

    public function scopeSearchForTenant(Builder $q, ?int $tenantId): Builder;
    public function scopeSearchForLocale(Builder $q, string $locale): Builder;
    public function scopeSearchOfType(Builder $q, string $typeHandle): Builder;
    public function scopeSearchInGroup(Builder $q, int|string|EntryGroup $g): Builder;
    public function scopeSearchOfSourceKind(Builder $q, string $kind): Builder;
    public function scopeSearchPublic(Builder $q): Builder;
    public function scopeSearchMatch(Builder $q, string $kw): Builder;     // emits MATCH(search_content) AGAINST(? IN BOOLEAN MODE)
}
```

### 6.7 `App\Models\Search\Collection` and nested pivots

```php
namespace App\Models\Search;

class Collection extends Model
{
    use \App\Traits\Search\HasSearchableGroups;

    protected $table = 'search_collections';
    public function searchEntryGroups(): BelongsToMany;
    public function searchFieldPriorities(): HasMany;
    public function searchTypes(): BelongsToMany;
}

namespace App\Models\Search\Collection;

class Group         extends Model { /* search_collection_entry_groups */ }
class FieldPriority extends Model { /* search_collection_field_priorities */ }
class Type          extends Model { /* search_collection_searchable_types */ }
```

### 6.8 Per-Group config models

```php
namespace App\Models\Search;

class Config extends Model
{
    protected $table = 'entry_group_search_configs';
    public function entryGroup(): BelongsTo;
}

class FieldPriority extends Model
{
    protected $table = 'entry_group_search_field_priorities';
    public function entryGroup(): BelongsTo;
    public function field(): BelongsTo;
}
```

### 6.9 `App\Traits\Searchable`

Applied to consumer models (Entry, User, future). Methods are `search`-prefixed where collision is plausible; the static query entry is just `search()` because that's its job and it's unambiguous on a model.

```php
namespace App\Traits;

trait Searchable
{
    public static function bootSearchable(): void;     // registers the model's observer

    public function searchDocuments(): MorphMany;      // → Search\Document
    public function searchType(): BelongsTo;           // → Search\Type, resolved via static $searchTypeHandle
    public function searchTypeHandle(): string;        // override-point on the model

    public function searchEnqueue(): void;             // queue indexing job
    public function searchReindex(): void;             // synchronous
    public function searchUnindex(): void;

    public function searchShouldIndex(): bool;         // delegates to Type::instance()
    public function searchLocales(): array;

    public static function search(string $keyword): \App\Services\Search\QueryBuilder;
}
```

### 6.10 `App\Traits\Search\HasSearchConfig`

Applied to `EntryGroup`.

```php
namespace App\Traits\Search;

trait HasSearchConfig
{
    public function searchConfig(): HasOne;
    public function searchFieldPriorities(): HasMany;
    public function searchEnsureConfig(): \App\Models\Search\Config;
    public function searchResolvedFields(): Collection;
    public function searchFieldPriorityFor(Field $f): ?float;    // returns the Group-level weight or null
    public function searchReindexAll(bool $queue = true): int;
}
```

### 6.11 `App\Traits\Search\HasSearchableGroups`

Applied to `Search\Collection`.

```php
namespace App\Traits\Search;

trait HasSearchableGroups
{
    public function searchAttachGroup(EntryGroup $g, int $sortOrder = 0): static;
    public function searchDetachGroup(EntryGroup $g): static;
    public function searchAttachType(Search\Type $t, float $weight = 1.0): static;
    public function searchDetachType(Search\Type $t): static;
    public function searchSetFieldWeight(EntryGroup $g, Field $f, float $weight): static;
    public function searchClearFieldWeight(EntryGroup $g, Field $f): static;
    public function searchResolvedFieldWeight(EntryGroup $g, Field $f): float;
}
```

### 6.12 Services

```php
namespace App\Services;

class SearchService extends AbstractService
{
    public function entries(): \App\Services\Search\QueryBuilder;
    public function members(): \App\Services\Search\QueryBuilder;
    public function ofType(string $handle): \App\Services\Search\QueryBuilder;
    public function collection(string|\App\Models\Search\Collection $c): \App\Services\Search\QueryBuilder;

    public function searchIndex(Model $m): void;
    public function searchUnindex(Model $m): void;
    public function searchReindexGroup(EntryGroup $g, bool $queue = true): int;
    public function searchReindexCollection(\App\Models\Search\Collection $c, bool $queue = true): int;
    public function searchReindexAll(bool $queue = true): int;
}

namespace App\Services\Search;

class TypeRegistry
{
    public function searchResolveByHandle(string $handle): \App\Search\AbstractSearchable;
    public function searchResolveByClass(string $modelClass): \App\Search\AbstractSearchable;
    public function searchAllTypes(): Collection;
}

class Indexer
{
    public function searchIndex(Model $m): void;
    public function searchIndexLocale(Model $m, string $locale): void;
    public function searchRemove(Model $m): void;
}

class QueryBuilder
{
    public function searchKeyword(string $term): static;
    public function searchIn(string|EntryGroup $g): static;
    public function searchOfType(string $type): static;
    public function searchInCollection(string|\App\Models\Search\Collection $c): static;
    public function searchLocale(string $l): static;
    public function searchTenant(?int $t): static;
    public function searchStatus(string $h): static;
    public function searchPublic(bool $only = true): static;
    public function searchWherePayload(string $key, mixed $value): static;
    public function searchOrderByRelevance(): static;
    public function searchLimit(int $n): static;

    public function get(): EloquentCollection;
    public function paginate(int $perPage = 20): LengthAwarePaginator;
    public function count(): int;
    public function searchExplain(): array;     // SQL + per-result component scores
}

class CollectionResolver
{
    public function searchResolve(string|\App\Models\Search\Collection $c): ResolvedCollection;
}

class KeywordParser
{
    public function searchParse(string $raw): string;     // boolean-mode formatter w/ allowlist
}
```

### 6.13 Observers, Jobs, Commands

```
app/Observers/Search/
  EntryObserver.php                   saved/deleted, plus reverse-Relationship lookup
  UserObserver.php
  FieldValueObserver.php              bubbles up to fieldable owner
  EntryRelationshipObserver.php       bubbles up to parent entry
  ConfigObserver.php                  reindexes group on config change
  CollectionObserver.php              invalidates resolver cache

app/Jobs/Search/
  ReindexModel.php                    single record, idempotent
  ReindexBatch.php                    chunked group/collection reindex
  PruneOrphans.php

app/Console/Commands/Search/
  SearchReindex.php                   php artisan search:reindex {--group=} {--type=} {--collection=} {--all}
  SearchPrune.php                     php artisan search:prune
  SearchStatus.php                    php artisan search:status — counts, stale rows, missing rows
```

## 7. Configuration Cascade

Two cascades, with different resolution timings.

### 7.1 Inclusion cascade — index-time

Determines whether a Field's value is *present* in the index at all. Resolved at index time and baked into the document by row presence/absence.

```
1. Field instance override   (fields.is_searchable)
        ↓ null
2. Field type default         (AbstractField::searchIsSearchable())
```

Per-Group and per-Collection levels do **not** affect inclusion. To suppress a Field's contribution in a specific Group or Collection, set its weight to `0` at that level (see §7.2). This keeps inclusion a single source of truth and avoids an explosion of reindex triggers when admins fiddle with Group/Collection settings.

### 7.2 Weight cascade — query-time

Determines how much a Field's text contributes to relevance for a given search. Resolved at query time via JOINs in the QueryBuilder. **Highest priority wins**:

```
1. Search\Collection field-priority override   (search_collection_field_priorities)
        ↓ no row
2. EntryGroup field-priority override           (entry_group_search_field_priorities)
        ↓ no row
3. Field instance override                      (fields.search_weight)
        ↓ null
4. Field type default                           (AbstractField::searchDefaultWeight())
```

Resolving weights at query time is the change that fixes the multi-Collection conflict: two Collections can apply different weights to the same Field on the same Entry without mutating the index. The cost is one or two LEFT JOINs per query, which MySQL handles cleanly because the priority tables are tiny (rows count in hundreds, not millions).

**What this means for reindex triggers:** weight changes at any level produce zero document writes. The next query simply picks up the new weight. The reindex pipeline only fires for changes that affect content presence (inclusion cascade, content edits, or row-level facets like status/published_at).

## 8. Weighting

Each row in `search_documents` is a single content unit with a single FULLTEXT column. A keyword search runs MATCH against every row, multiplies each row's match score by its resolved weight, then SUMs per searchable to produce final relevance.

The QueryBuilder emits one of two query shapes depending on whether the caller scoped to a Search Collection. The shapes are different — not the same query with optional joins — because Collection scope **restricts** the result set, not just the weight cascade.

### 8.1 Direct query — `Search::entries()` / `Search::ofType()` / model-side `::search()`

No Collection scope. Weight cascade has only three levels (group, field-instance, field-type default).

```sql
SELECT
    d.searchable_id,
    d.searchable_type,
    SUM(
        MATCH(d.search_content) AGAINST(:keyword IN BOOLEAN MODE)
        * COALESCE(
            egfp.search_weight,                      -- group-level override
            f.search_weight,                         -- field-instance override
            CASE d.source_kind
                WHEN 'title'    THEN :w_title
                WHEN 'handle'   THEN :w_handle
                WHEN 'category' THEN :w_category
                WHEN 'related'  THEN :w_related
                WHEN 'tag'      THEN :w_tag
                ELSE :w_field_default                -- looked up via field_id → field_type
            END
        )
        * :status_multiplier
        * :freshness_multiplier
    ) AS relevance
FROM search_documents d
LEFT JOIN fields f
    ON d.field_id = f.id AND d.field_id <> 0
LEFT JOIN entry_group_search_field_priorities egfp
    ON egfp.entry_group_id = d.entry_group_id
   AND egfp.field_id       = d.field_id
WHERE (d.tenant_id IS NULL OR d.tenant_id = :tenant_id)
  AND d.locale = :locale
  AND MATCH(d.search_content) AGAINST(:keyword IN BOOLEAN MODE)
  -- ... additional facet filters from searchWherePayload, status, etc.
GROUP BY d.searchable_id, d.searchable_type
ORDER BY relevance DESC
LIMIT :per_page OFFSET :offset
```

### 8.2 Collection-scoped query — `Search::collection()`

Restricts results to:

1. The Collection's allowed Searchable Types (via `INNER JOIN search_collection_searchable_types`).
2. For Entry-typed rows: the Collection's attached Entry Groups (via `LEFT JOIN search_collection_entry_groups` plus a WHERE clause that lets through type-less rows like Users while requiring an attached-group match for Entry rows).

Adds the four-level weight cascade (collection > group > field-instance > type default) and the inter-type modifier from `search_collection_searchable_types.search_weight`.

```sql
SELECT
    d.searchable_id,
    d.searchable_type,
    SUM(
        MATCH(d.search_content) AGAINST(:keyword IN BOOLEAN MODE)
        * COALESCE(
            ccfp.search_weight,                      -- collection-level override
            egfp.search_weight,                      -- group-level override
            f.search_weight,                         -- field-instance override
            CASE d.source_kind
                WHEN 'title'    THEN :w_title
                WHEN 'handle'   THEN :w_handle
                WHEN 'category' THEN :w_category
                WHEN 'related'  THEN :w_related
                WHEN 'tag'      THEN :w_tag
                ELSE :w_field_default
            END
        )
        * scst.search_weight                         -- inter-type modifier from the Collection
        * :status_multiplier
        * :freshness_multiplier
    ) AS relevance
FROM search_documents d
INNER JOIN search_collection_searchable_types scst
    ON scst.search_collection_id = :collection_id
   AND scst.search_type_id        = d.search_type_id
LEFT JOIN search_collection_entry_groups scg
    ON scg.search_collection_id = :collection_id
   AND scg.entry_group_id        = d.entry_group_id
LEFT JOIN fields f
    ON d.field_id = f.id AND d.field_id <> 0
LEFT JOIN entry_group_search_field_priorities egfp
    ON egfp.entry_group_id = d.entry_group_id
   AND egfp.field_id       = d.field_id
LEFT JOIN search_collection_field_priorities ccfp
    ON ccfp.search_collection_id = :collection_id
   AND ccfp.entry_group_id       = d.entry_group_id
   AND ccfp.field_id             = d.field_id
WHERE (d.tenant_id IS NULL OR d.tenant_id = :tenant_id)
  AND d.locale = :locale
  AND (d.entry_group_id IS NULL OR scg.id IS NOT NULL)    -- Entry rows must match an attached group; type-less rows pass
  AND MATCH(d.search_content) AGAINST(:keyword IN BOOLEAN MODE)
GROUP BY d.searchable_id, d.searchable_type
ORDER BY relevance DESC
LIMIT :per_page OFFSET :offset
```

The `INNER JOIN` to `search_collection_searchable_types` is the key correctness piece — without it, `Search::collection('site-search')` would scan every Entry and User in the index while only borrowing the Collection's field-weight overrides. The INNER JOIN restricts the candidate set to types the Collection actually allows.

`CollectionResolver` is responsible for rejecting inactive Collections (`is_active = false`) and for resolving the locale binding: if `search_collections.locale` is set, that's the bound `:locale`; otherwise the QueryBuilder's `searchLocale()` (or the request locale) is bound. `is_active` is a service-layer concern, not a query-layer one — searching against an inactive Collection should fail loudly at resolution time, not return zero rows from a successful query.

### 8.3 Notes on both queries

- All `MATCH … AGAINST` calls use `IN BOOLEAN MODE` consistently. The `KeywordParser` produces boolean-mode terms; the WHERE clause and the SELECT clause must agree on the mode or scoring is meaningless.
- The inner `WHERE … MATCH` is the FULLTEXT pre-filter — MySQL only computes the SELECT-side MATCH on rows that already passed the WHERE. Per-row scoring is therefore bounded by the keyword's selectivity, not by total table size.
- `:w_field_default` resolves through a small lookup against the Field's type. This can be a JOIN to a `search_type_default_weights` view or, for simplicity, baked into a CASE expression generated from `config/search.php`.
- The `LEFT JOIN fields f ON d.field_id = f.id AND d.field_id <> 0` clause uses the `<> 0` predicate to skip the sentinel — synthetic rows have `field_id = 0` (no `fields` row exists with id 0), so the JOIN naturally produces a NULL `f.search_weight` and the COALESCE falls through to the source-kind CASE. Without the `<> 0` predicate the join would still LEFT-NULL but would scan the `fields` table looking for id 0 on every row.
- **The tenant clause is parenthesized.** `(d.tenant_id IS NULL OR d.tenant_id = :tenant_id)` is wrapped because in SQL `AND` binds tighter than `OR`; without the parens, every tenantless row would short-circuit past the locale, MATCH, and facet predicates. The QueryBuilder must always emit the parens, and both engines (single-tenant fixture and multi-tenant fixture) must be exercised in the test suite. As an alternative that sidesteps the precedence trap entirely, the QueryBuilder may emit two distinct WHERE shapes — `d.tenant_id = :tenant_id AND ...` for tenant-scoped queries and `d.tenant_id IS NULL AND ...` for super-admin / pre-tenancy queries — chosen by the caller's context. Either is acceptable; mixing them in one expression without parens is not.

Optional multipliers:

- **Freshness decay** — `1.0 + 0.5 * exp(-days_since_published / half_life)`, capped at `1.5x`. Off by default; configured per-Group via `entry_group_search_configs.search_apply_freshness` + `search_freshness_half_life`. Applied in the SELECT as a column expression on `published_at`.
- **Status multiplier** — configurable map: `published 1.0`, `featured 1.5`, `archived 0.3`, others `0.5`. Configured globally in `config/search.php`, with per-Group override via `entry_group_search_configs.search_status_multipliers` JSON. Applied as a `CASE` on `status_handle`.

Both multipliers run inside the SQL, not in a PHP scoring loop.

## 9. Indexing Pipeline

Triggers — these are the *correct* hooks, the ones that catch real edits. The list separates **content** triggers (which write rows) from **config** triggers (which mostly invalidate caches now that weight resolution is query-time).

### 9.1 Content triggers (write `search_documents` rows)

1. `Entry::saved` / `deleted` → reindex the Entry's own rows (title, handle, category links, relational denormalizations, field-source rows). Hard delete only — Entry does not use `SoftDeletes`. If `SoftDeletes` is added later, the observer also handles `restored` / `forceDeleted`; this is a one-line change.
2. `User::saved` / `deleted` → reindex the User. Reads `users.is_searchable` to decide whether to index at all (see §5.10).
3. `FieldValue::saved` / `deleted` → resolve the fieldable owner via morphTo, refresh that owner's `source_kind='field'` row for the affected field. *This is the most important hook* — almost all content edits flow through `FieldValue`, and an observer on `Entry` alone misses every one of them.
4. `EntryRelationship::saved` / `deleted` → refresh the parent Entry's `source_kind='related'` rows for that Relationship field. If the Relationship type contributes a `source_kind='field'` row of its own (per `Relationship::searchToString()`), refresh that too.
5. **Reverse-Relationship trigger** — `Entry::saved` (additionally): look up `entry_relationships WHERE related_entry_id = $entry->id`, and for each parent Entry, refresh the corresponding `source_kind='related'` row. This is the fix for "Entry A indexes Entry B's title; B's title changes; A's index now stale." A query keyed by `related_entry_id` returns the small set of parents that need touching; only their `related` rows update, not the whole document.
6. **Category::saved / deleted** — when a Category is renamed: `UPDATE search_documents SET search_content = :new_name WHERE source_kind = 'category' AND source_type = 'category' AND source_id = :category_id`. Targeted, no per-Entry reindex needed for a rename. On Category delete: same `WHERE` clause with `DELETE`.
7. **Categorizable attach/detach** — explicit, **not** implicit. The current write path is `$entry->categories()->sync($categoryIds)` in `EntryRepository::syncCategories()`, and `HasCategories` is a plain `morphToMany` with no `using()` declaration. Eloquent's pivot events (`pivotAttached` / `pivotDetached`) only fire when a custom Pivot class is in use, so subscribing to them today would be a silent no-op. Two acceptable paths, in order of preference:
   - **Repository hook** — extend `EntryRepository::syncCategories()` to compute the attached/detached id diffs from the `sync()` return value (`['attached' => [...], 'detached' => [...], 'updated' => [...]]`) and dispatch a typed `CategoriesSynced` domain event after the sync. The search observer subscribes to this event and applies row inserts/deletes.
   - **Custom Pivot model** (future option) — introduce `App\Models\Categorizable` with `using()` on the morph relation, register a model observer on it, and let Eloquent fire pivot events naturally. Heavier, but the right move if other concerns also need pivot-side state (timestamps, ordering, audit).
   The first path is recommended for now — minimal surface change, no behavior shift in the existing repository semantics, and verifiable in a unit test against the actual `sync()` return shape rather than assumed framework events.
8. Status transition to a non-public status → call `searchUnindex()` (removes all rows for that searchable). Hard delete of the Entry/User → same.
9. **Cleanup-on-source-delete** — because `field_id` and `source_id` carry no DB-level FK constraints (§5.2.0), the search system is responsible for purging rows whose source object no longer exists. Four observer hooks cover the cases:
   - `Field::deleting` → `DELETE FROM search_documents WHERE field_id = :id` (covers both `source_kind='field'` rows and `source_kind='related'` rows whose Relationship field was deleted).
   - `Category::deleting` → `DELETE FROM search_documents WHERE source_type = 'category' AND source_id = :id`.
   - `Tag::deleting` → `DELETE FROM search_documents WHERE source_type = 'tag' AND source_id = :id`.
   - `Entry::deleting` (in addition to triggers 1 and 5) → `DELETE FROM search_documents WHERE source_type = 'entry' AND source_id = :id`. This purges `source_kind='related'` rows in *other* Entries that pointed at the one being deleted; the Entry's own rows are removed by trigger 1's standard `searchUnindex()` call.

### 9.2 Config triggers (cache invalidation, occasional reindex)

9. `Search\Config` saved (per-Group toggles) → if `is_indexable` flipped, reindex/unindex the whole Group; if `include_categories` / `include_related` / `include_handle` changed, reindex (these affect row presence). If only freshness/status/locale changed, no reindex — query-time concerns.
10. `Search\Collection` saved / pivot changes → invalidate `CollectionResolver` cache. **No reindex** — Collection field-priority overrides are query-time only (this is the §7 design choice that fixes the multi-Collection conflict).
11. `Field::is_searchable` changed → reindex Groups that use this Field (rows added or removed). `Field::search_weight` changed → no reindex; query-time.
12. `entry_group_search_field_priorities` row added/changed/removed → if it's a weight change, no reindex; query-time. (There is no bucket column here anymore, so there's no row-presence-affecting axis at this level.)

All write-triggering paths default to queuing (`SEARCH_QUEUE=true`, configurable). Single-record reindex is idempotent: it computes the new rows and `updateOrInsert`s on the unique `(searchable_id, searchable_type, locale, source_kind, field_id, source_id)` key per row. Batch reindex chunks by ID range and is interrupt-safe — reruns resume cleanly.

Concurrency: a `searchable_id`-keyed Redis lock with a 30s TTL wraps the per-model indexer to prevent duplicate work when multiple jobs are queued for the same target. The unique key already protects correctness; the lock is for efficiency.

## 10. Query Layer

```php
// Direct keyword search in a Group
Search::entries()
    ->searchIn('blog')
    ->searchKeyword('laravel testing')
    ->searchPublic()
    ->paginate(20);

// Member (User) search
Search::members()
    ->searchKeyword('john')
    ->paginate(15);

// Search inside a Collection — resolves the Collection's Groups + Types,
// applies collection-level priority overrides, runs one query per Type,
// merges by computed relevance.
Search::collection('site-search')
    ->searchKeyword('migration')
    ->searchLocale('en')
    ->paginate(20);

// On a Searchable model directly
Entry::search('laravel')->searchInCollection('blog-only')->get();
User::search('admin')->searchPublic()->get();
```

`KeywordParser::searchParse()` normalizes user input:

- Lowercase.
- Strip non-alphanumeric except space and hyphen.
- Drop terms shorter than `config('search.min_term_length', 3)`.
- Cap term count at `config('search.max_terms', 16)`.
- Add `+` prefix to required terms.
- Throw a typed exception if the result is empty.

This eliminates the boolean-mode injection surface that ad-hoc concatenation creates. "Advanced syntax" (quoted phrases, `-` exclusions) ships behind a feature flag in a later phase if needed.

`searchExplain()` returns the rendered SQL plus the per-result component scores (`title_match`, `body_match`, `meta_match`, `freshness`, `status`, `final`) so debugging "why does X rank #5" is one method call instead of a debug session.

## 11. Phases

Each phase is an independently shippable increment. The phases are sequenced so that stopping after any one of them leaves a coherent, working system at that scope.

### Phase 0 — Spike (2–3 days)

**This is the key decision point for the entire plan.** The per-content-unit document model is more internally consistent for Collection weighting than the original three-bucket idea, but it is a bigger query-design commitment: the production query path is `MATCH … AGAINST` plus a four-table LEFT JOIN cascade plus `SUM` plus `GROUP BY`. Before any migrations land, the spike must prove that shape works at projected scale. If it doesn't, every later phase rests on a foundation that needs replacing.

What the spike must produce:

- A throwaway `search_documents`-shaped table (per §5.2 columns and indexes, including the FULLTEXT on `search_content` and the composite `(source_type, source_id)` index used for reverse-Relationship lookups).
- Throwaway `entry_group_search_field_priorities` and `search_collection_field_priorities` tables populated with realistic priority overrides.
- A populator script that walks current Entries + FieldValues + EntryRelationships + Categorizables and produces representative content-unit rows. **Target row counts**: 10K Entries × ~12 rows = 120K rows; 100K Entries × ~12 rows = 1.2M rows. Run both.
- The literal production query — `MATCH(d.search_content) AGAINST(:kw IN BOOLEAN MODE)` in WHERE *and* in SELECT, with the full `COALESCE(ccfp.weight, egfp.weight, f.weight, default)` cascade, `SUM` aggregation, `GROUP BY (searchable_id, searchable_type)`, status multiplier `CASE`, freshness expression on `published_at`, paginated.
- Latency measurements at p50 / p95 / p99 for: single-term, two-term, four-term, four-term + status filter + facet filter from `search_payload`, four-term + Collection scope (extra JOIN), four-term + Collection scope on a 1.2M-row table.

InnoDB-specific homework done in the same window:

- Confirm InnoDB FULLTEXT is enabled.
- Tune `innodb_ft_min_token_size` if 3-letter terms (`php`, `sql`, `c++`) are needed for the corpus.
- Decide on stop-word policy (use the InnoDB default list, or replace with a custom one).
- Verify `MATCH AGAINST` behavior under multi-byte/UTF-8 collations matching current Entry titles.

**Decision gate** — explicit thresholds before Phase 1 begins:

| Measurement | Target | If exceeded |
|---|---|---|
| p95 latency at 1.2M rows, single term | ≤ 100 ms | Profile JOIN + GROUP BY plan; consider denormalizing the priority resolution into a generated column. |
| p95 latency at 1.2M rows, four-term + Collection scope | ≤ 250 ms | Same. |
| p99 latency on either of the above | ≤ 500 ms | If still over after profiling, **abort native FULLTEXT path and pivot to Meilisearch from Phase 1.** Plan the swap before any production migrations exist; this is the cheapest moment to change direction. |
| Reindex throughput, populator script | ≥ 5K rows/sec | Below this, batch reindex jobs in Phase 4 may not keep up with realistic edit rates; tune the indexer or accept queue lag. |

The Meilisearch fallback is not a defeat — it's the same data model expressed against an engine that handles the JOIN-and-SUM pattern natively (per-attribute weighting, query-time `attributesToSearchOn` overrides, no SQL gymnastics). The phasing in §11 is engine-agnostic from Phase 1 onward; only the QueryBuilder implementation in Phase 5 differs. What Phase 0 buys is the right to commit to native FULLTEXT — or to know early that we shouldn't.

### Phase 1 — Foundation (week 1)

A registry, a Document, a trait skeleton — no live indexing yet.

- Migrations:
  - `create_search_types_table`
  - `create_search_documents_table` (per-content-unit shape from §5.2)
  - `add_search_columns_to_fields_table` (`is_searchable`, `search_weight`)
  - `add_search_columns_to_users_table` (`is_searchable` default `false`, `search_weight`) — User search is opt-in (§5.10, §14)
- Models: `Search\Type`, `Search\Document`.
- Abstract: `Search\AbstractSearchable`.
- Trait skeleton: `Searchable`.
- Seeder: `SearchTypeSeeder` registers `entry` and `user`.
- Tests: model relations, Document scopes, factories, `users.is_searchable` default behavior.

**Done = a `Search\Document` row can be created by hand, related to either an Entry or a User, and queried via the Document scopes.**

### Phase 2 — Field-layer integration (week 1–2)

Each Field type knows whether and how it contributes to the index.

- Extend `AbstractField` with the four search methods.
- Implement defaults per concrete type (Text, Textarea, EmailAddress, Url, Telephone, Relationship; non-searchable for Number/Date/Boolean/ColorPicker).
- Tests: per-type defaults, instance-level override via `fields.is_searchable` / `search_weight`.

**Done = `$fieldType->instance()->searchToString($value)` returns the right thing for every type.**

### Phase 3 — Indexer + Searchable types (week 2)

An Entry or User can produce a document. Nothing reads it yet.

- `Search\Types\EntrySearchable` (delegates to `EntryRepository::resolveLayoutFields`, handles relational fields).
- `Search\Types\UserSearchable`.
- `Search\Indexer` and `Search\TypeRegistry`.
- `Searchable` trait applied to `Entry` and `User`.
- Tests: indexed content lands in the right `source_kind` rows with the right `field_id` / `source_id` / `source_type`; relational fields produce `source_kind='related'` rows carrying the related Entry's title and a stable `(source_type='entry', source_id=...)` reference; non-searchable Field types are excluded.

**Done = `Search::entries()->searchIndex($entry)` writes a correct row.**

### Phase 4 — Observer pipeline (week 2–3)

Indexes stay in sync with live data — *every* edit path, including the reverse-direction ones.

- `Observers\Search\EntryObserver`, `UserObserver`, `FieldValueObserver`, `EntryRelationshipObserver`.
- `Observers\Search\CategoryObserver` — Category rename → targeted UPDATE on `source_kind='category'` rows; Category `deleting` → targeted DELETE on rows keyed by `(source_type='category', source_id=$category->id)`. The DELETE is the §5.2.0 referential-integrity contract — no DB FK does this for us.
- `Observers\Search\FieldObserver` — Field `deleting` → `DELETE FROM search_documents WHERE field_id = :id`. Covers both the Field's own `source_kind='field'` rows and any `source_kind='related'` rows that used this Field as a Relationship.
- `Observers\Search\TagObserver` — Tag `deleting` → `DELETE FROM search_documents WHERE source_type = 'tag' AND source_id = :id`.
- `EntryObserver::deleting` extension — beyond the standard `searchUnindex()` for the deleted Entry's own rows, also `DELETE FROM search_documents WHERE source_type = 'entry' AND source_id = :id` to purge `source_kind='related'` rows in *other* Entries that pointed at the one being deleted.
- **Explicit categorizable hook in `EntryRepository::syncCategories()`** — read the `attached`/`detached`/`updated` arrays from `sync()`'s return value and dispatch a `CategoriesSynced` domain event. Search observer subscribes and applies row inserts/deletes. *Do not* rely on Eloquent pivot events — `HasCategories` declares no `using()` class, so they don't fire.
- Reverse-Relationship hook in `EntryObserver::saved` — `SELECT entry_id FROM entry_relationships WHERE related_entry_id = $entry->id`, then for each parent and each Relationship field, refresh the corresponding `source_kind='related'` row by `(source_type='entry', source_id=$entry->id, field_id=$field->id)`.
- `Jobs\Search\ReindexModel`.
- Tests covering each trigger plus the unique-key collision cases and source-delete cleanup the new schema is designed to enforce:
  - Editing a `FieldValue` updates the field's row.
  - Status change to non-public unindexes.
  - Hard-deleting an Entry removes all of its rows AND every `source_kind='related'` row in other Entries that pointed at it (`Entry::deleting` cleanup hook).
  - `EntryRelationship` add/remove updates parent's `related` rows.
  - Changing Entry B's title updates Entry A's `related` row when A references B (reverse-trigger smoke test).
  - Renaming a Category updates every `category` row pointing at that `category_id` — confirms the source-id-based update keeps working when handles are non-unique across groups.
  - **Deleting a Field cascades to all `field`-kind rows AND all `related`-kind rows whose Relationship field was the deleted one** (no DB FK does this for us, so the observer must).
  - **Deleting a Category cascades to all `category`-kind rows pointing at that category_id.**
  - **Deleting a Tag cascades to all `tag`-kind rows pointing at that tag_id.**
  - Toggling `users.is_searchable` adds/removes that User's documents.
  - **Two Relationship fields on the same Entry both pointing at the same related Entry produce two distinct rows (no unique-key collision).**
  - **Two same-handle Categories from different Category Groups attached to the same Entry produce two distinct rows.**
  - **`CategoriesSynced` event fires from `EntryRepository::syncCategories()` with correct attached/detached id arrays** — verifies the explicit hook, since the implicit pivot-event path is intentionally not used.

**Done = no edit path leaves the index stale, including the reverse-direction edits and the categorical collisions the original design missed.**

### Phase 5 — Query layer (week 3)

Working keyword search with proper weighting.

- `Search\KeywordParser` (allowlist, term cap, min length).
- `Search\QueryBuilder` (fluent, weighted MATCH, freshness/status multipliers).
- `SearchService::entries()` + `::members()`.
- `Searchable::search()` static.
- Pagination, ordering, payload-facet filters, `searchExplain()`.
- Tests: relevance ordering, multi-term, locale isolation, status filtering, injection-attempt strings.

**Done = end-to-end keyword search ships. This is the first user-visible milestone.**

### Phase 6 — EntryGroup configuration (week 4)

Per-Group field weights and toggles via admin UI.

- Migrations: `entry_group_search_configs`, `entry_group_search_field_priorities`.
- Models: `Search\Config`, `Search\FieldPriority`.
- Trait: `HasSearchConfig` applied to `EntryGroup`.
- `Observers\Search\ConfigObserver` queues full-group reindex only when row-presence-affecting flags change (`is_indexable`, `include_categories`, `include_related`, `include_handle`); pure weight-config changes invalidate cache only.
- QueryBuilder JOINs against `entry_group_search_field_priorities` for weight cascade resolution.
- Admin UI: per-Group field-priority editor (mirrors FieldLayout admin UX).
- Tests: weight cascade resolution; weight = 0 contributes 0 to relevance but row stays in index; flipping `is_indexable` removes/restores all rows for the Group; weight-only changes do not trigger reindex.

**Done = admins can configure search per Entry Group without code.**

### Phase 7 — Collections (week 4–5)

Composable, multi-Group, multi-Type search scopes with priority overrides.

- Migrations: 4 collection tables.
- Models: `Search\Collection`, `Search\Collection\Group`, `Search\Collection\FieldPriority`, `Search\Collection\Type`.
- Trait: `HasSearchableGroups`.
- `Search\CollectionResolver` (cached, invalidated by `CollectionObserver`). Resolver rejects inactive Collections at lookup time, throws `InactiveCollectionException`. Resolver also resolves the locale binding (collection.locale wins over caller-supplied locale; falls back to request locale).
- `SearchService::collection()` + QueryBuilder `searchInCollection()`. QueryBuilder emits the §8.2 query shape — INNER JOIN through `search_collection_searchable_types` and the `(d.entry_group_id IS NULL OR scg.id IS NOT NULL)` predicate that restricts Entry rows to the Collection's attached Groups while letting type-less rows (Users) pass.
- Admin UI: collection CRUD, group attachment with sort order, Type attachment, weight override editor.
- Tests covering correctness of the Collection scope, not just the weight cascade:
  - **A document whose Entry Group is NOT attached to the Collection does NOT appear in the result set**, even if its `search_content` matches the keyword. Confirms the `INNER JOIN scst` + group-presence predicate actually restrict, not just borrow weights.
  - **A document whose Searchable Type is NOT attached to the Collection does NOT appear**. (Add Entry to Collection but not User; search a keyword that matches both an Entry and a User; only the Entry returns.)
  - **Inactive Collection raises at the resolver, not at the query layer** — `Search::collection('disabled-handle')` throws before any SQL runs.
  - Cross-Group weight isolation: same Field used in two Groups inside one Collection accepts independent override weights via the `(collection_id, entry_group_id, field_id)` key.
  - Multi-type result merge: an Entry and a User both matching the same keyword rank together, with the inter-type modifier from `search_collection_searchable_types.search_weight` applied.
  - Locale filtering: a Collection with `locale='en'` returns only `en` documents; a Collection with `locale=NULL` returns documents in any locale.
  - Collection-level field-weight override beats Group-level beats Field-instance beats Field-type default — full cascade verified at query time, with the same Field instance scoring differently in two Collections that include the same Group.

**Done = the user-facing requirement for composable Collections is delivered, and the Collection scope provably restricts the result set rather than just decorating it with weights.**

### Phase 8 — API endpoints (week 5)

Public read API for keyword + collection search.

- `GET /api/v1/search` — keyword search by group/type.
- `GET /api/v1/search-collections/{handle}/search` — collection search.
- API resources for `Search\Document` and merged result rows.
- Rate limiting per Sanctum token.
- OpenAPI annotations.

**Done = external callers can search.**

### Phase 9 — Tenancy + locale safety (deferred — runs when tenancy lands)

- Apply `BelongsToTenant` global scope to all search models.
- Backfill `tenant_id` on `search_documents`.
- Cross-tenant isolation tests.
- If `spatie/laravel-translatable` is enabled on Entry titles/fields, expand Indexer to write one document per locale; QueryBuilder defaults to the request locale.

### Phase 10 — Engine swap (deferred — only if metrics demand)

- `composer require laravel/scout`.
- Custom Scout engine wrapping `search_documents` so model-side API doesn't change.
- Optional: `SCOUT_DRIVER=meilisearch` or `typesense` — config flip, no app code change.

## 12. Configuration Surface (Admin UI)

Three admin areas, mirroring patterns already in the codebase.

### 12.1 EntryGroup → Search tab

A new tab on the existing EntryGroup edit screen. UI shows the group's resolved field layout with:

- Weight slider/input per Field (`0.0` – `5.0`, step `0.25`). A weight of `0` keeps the field in the index but contributes nothing to relevance for this Group; to remove the field from the index entirely, the admin sets `is_searchable = no` on the Field instance itself (see §12.3).
- Inline indicator: "inherits from field type default" vs. "overridden here".

Plus group-level toggles: master kill-switch (`is_indexable`), freshness decay (with half-life input), default locale, status-multiplier overrides, and the inclusion checkboxes from §5.3 (`include_title`, `include_handle`, `include_categories`, `include_related`).

### 12.2 `/admin/search/collections`

Top-level Collection management, mirroring `/admin/entry-groups`.

Per-Collection edit screen has three tabs:

1. **General** — name, handle, description, locale, active flag.
2. **Sources** — attached Entry Groups (sortable by `sort_order`), attached Searchable Types (Entry, User), per-type weight.
3. **Field Priorities** — per-(Group, Field) weight override editor; defaults to "inherit" with an explicit "override" toggle. Greyed out for any field that has `is_searchable = false` at the Field-instance or Field-type level (no row exists in the index, so a Collection-level weight override would have nothing to weight).

### 12.3 Field instance override

Two new inputs added to the existing Field edit screen: **searchable (override)** dropdown (`Inherit` / `Yes` / `No`) and **weight (override)** input.

## 13. Testing Plan

Pyramid by phase. Every phase ships with tests that cover its delta.

- **Unit** — every model, trait method, service method; every `AbstractField` subclass override; every `QueryBuilder` method; every `KeywordParser` allowlist edge case; weight-cascade resolver with all four levels populated.
- **Feature** — full save → observer → index round-trip; edit → update → search hit; hard-delete → unindex; configuration cascade resolution; reverse-Relationship staleness fix; Category-rename propagation; opt-in/out via `users.is_searchable`.
- **Integration** — per-phase smoke tests against a seeded fixture (one EntryGroup, three Entries with mixed field types — one of which has a Relationship pointing at another, and shared Categories — three Users, one Collection composing multiple Groups with override weights set).
- **Performance** — at end of Phase 5, baseline keyword query latency at 10K and 100K rows on the per-content-unit shape (which produces ~10–15× the row count of the original per-model shape but only one MATCH per row). Gate proceeding past Phase 7 on this number.

## 14. Open Decisions

These need a call before Phase 1 starts.

1. **MySQL FULLTEXT vs Meilisearch from day one.** Phase 0 spike informs this. Default recommendation: native FULLTEXT, defer Meilisearch to Phase 10. The Phase 10 swap is non-breaking because the model-side API doesn't change.
2. **Include `tenant_id` column now or later.** Recommended: include the column nullable in Phase 1 migrations; populate when tenancy lands. Cheaper than `ALTER TABLE` on what may eventually be a large table.
3. **Translatable fields.** If `spatie/laravel-translatable` is going to be turned on for Entry titles and text fields in the near term, build per-locale documents from Phase 1. If not, default-locale only and revisit in Phase 9.
4. **Status multipliers — global config or per-group config.** Recommended: global default in `config/search.php`, per-Group override via `entry_group_search_configs.search_status_multipliers` JSON. Keeps Phase 6 simple while leaving room for tuning.
5. **Whether `User` is searchable by default.** Users carry private data. Recommended: User searchability is opt-in via a `users.is_searchable` flag and gated by a permission check in QueryBuilder so that admin search ≠ public search.
6. **Concurrency model for reindexing.** With the composite unique key on `(searchable_id, searchable_type, locale, source_kind, field_id, source_id)`, `updateOrInsert` is correct per content unit even under concurrent indexing of the same model. Two workers reindexing the same Entry will land on the same rows and the second will UPDATE instead of INSERT. Correctness is protected; what's wasted is duplicate work. Recommendation: a `(searchable_type:searchable_id)`-keyed Redis lock with 30s TTL inside the Indexer, so only one worker per model is computing rows at a time. The lock is for efficiency, not correctness — a Redis outage degrades to "may do duplicate work" rather than "may corrupt the index."

## 15. Risks

- **Row count expansion** — per-content-unit decomposition multiplies row count by approximately the number of indexable Fields per Entry plus a handful of synthetic rows (title, handle, categories, related). For a typical Entry with 6 searchable Fields and 3 categories that's roughly 12 rows per Entry per locale. At 100K Entries × 1 locale that's ~1.2M index rows; at 1M Entries it's ~12M. MySQL FULLTEXT handles 12M rows comfortably; Phase 5 baseline measurements gate proceeding past Phase 7.
- **GROUP BY on FULLTEXT result sets** — the per-row scoring + SUM aggregation requires a GROUP BY, which on large result sets can spill to disk. Mitigations: the FULLTEXT WHERE pre-filters before GROUP BY (so the aggregated set is bounded by keyword selectivity), and the QueryBuilder caps `LIMIT * 10` candidates pre-aggregation when relevance ordering is requested.
- **Reverse-Relationship trigger fan-out** — saving an Entry that is referenced by many other Entries triggers a refresh of every parent's `related` row. Bounded by the count of inbound relationships, which is typically small. Capped and queued; if the parent count exceeds a threshold, the work is dispatched as a `ReindexBatch` job rather than inline.
- **Status multiplier interactions** — if `archived = 0.3x` is too low, archived content effectively never surfaces. Configurable, with a "minimum visible relevance" threshold so `0.3x` doesn't always rank below excluded.
- **Boolean-mode parser drift** — user-supplied operators are seductive for power users and dangerous for everyone else. Strict allowlist; advanced syntax behind a flag.
- **Trait method collisions** — the `Search` prefix mitigates, but `search()` static and `searchDocuments()` relation are short enough to occasionally collide. Names chosen to be unambiguous in their host-model contexts.

## 16. Future Extensions (post-Phase 10)

- **Synonyms / stemming** — analyzer config; easier on Meilisearch.
- **Faceted aggregation** — counts by category, type, status. Cleanest in an external engine.
- **Personalized ranking** — per-user signals (clicked, dismissed). Adds a `search_signals` table.
- **Search analytics** — log queries to a `searches` table for popular-query / zero-result tracking.
- **Auto-complete** — separate prefix index; not part of this design.

---

This plan is structured so each phase is shippable on its own. Phases 1–5 deliver working keyword search on Entries and Users with cascading per-Field weight control. Phase 6 adds the per-Group admin UI. Phase 7 delivers the Collection composition. Everything beyond is optional and reversible.
