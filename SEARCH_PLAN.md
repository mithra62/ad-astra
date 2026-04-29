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

The polymorphic index. One row per (searchable, locale). The three FULLTEXT columns are intentional — proper per-bucket weighting in MySQL requires separate columns, not a single concatenated `content` field with delimiters.

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

search_title              varchar(500)            high-weight bucket
search_body               mediumText              primary-weight bucket
search_meta               mediumText              low-weight bucket: categories, related titles, tags

status_handle             string nullable
is_public                 boolean default false
published_at              timestamp nullable
search_payload            json                    arbitrary searchable + facetable attrs

search_indexed_at         timestamp
timestamps

unique  (searchable_id, searchable_type, locale)
index   (tenant_id, searchable_type, is_public, published_at)
index   (entry_group_id, locale)
fulltext (search_title)
fulltext (search_body)
fulltext (search_meta)
```

The unique key on `(searchable_id, searchable_type, locale)` makes `updateOrInsert` safe under concurrent indexing.

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

Per-Group, per-Field weight + bucket. The original-docs split between an "is enabled" boolean and a separate weight is collapsed: `search_weight = 0` means disabled. One axis, one place to look.

```
entry_group_search_field_priorities
─────────────────────────────
id
entry_group_id      fk cascadeOnDelete
field_id            fk cascadeOnDelete
search_weight       decimal(4,2) default 1.00
search_bucket       enum('title','body','meta') default 'body'
timestamps
unique (entry_group_id, field_id)
```

### 5.5 `search_collections`

A named, composable search scope.

```
search_collections
─────────────────────────────
id
tenant_id        fk nullable
name             string
handle           string
description      text nullable
locale           string(8) nullable      null = all locales
is_active        boolean default true
timestamps
unique (tenant_id, handle)
```

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
search_bucket          enum('title','body','meta') nullable    null = inherit Group bucket
timestamps
unique (search_collection_id, entry_group_id, field_id)
```

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

    /** Should the given model be indexed at all right now? Status, soft-delete, public flag. */
    abstract public function searchShouldIndex(Model $model): bool;

    /** Locales this model should produce documents for. Default ['en']; translatable models override. */
    public function searchLocales(Model $model): array;

    /** The Field models whose values participate in indexing. Implementations resolve from layout. */
    abstract public function searchFieldsFor(Model $model): Collection;

    /** Build a single document row for (model, locale). Returns array shaped to search_documents. */
    abstract public function searchToDocument(Model $model, string $locale): array;

    /** Hook for subclasses to layer on category names, related entry titles, tags, etc. */
    protected function searchAdditionalMeta(Model $model, string $locale): array;
}
```

### 6.2 `App\Search\Types\EntrySearchable`

```php
namespace App\Search\Types;

class EntrySearchable extends \App\Search\AbstractSearchable
{
    protected string $handle = 'entry';
    protected string $name = 'Entry';

    public function searchShouldIndex(Model $entry): bool;
    public function searchFieldsFor(Model $entry): Collection;       // delegates to EntryRepository::resolveLayoutFields
    public function searchToDocument(Model $entry, string $locale): array;
    protected function searchAdditionalMeta(Model $entry, string $locale): array;
}
```

`searchFieldsFor()` reuses the existing `EntryRepository::resolveLayoutFields()` so the same precedence (EntryType layout > EntryGroup layout) that drives field persistence drives indexing. Relational fields are pulled via `Entry::field($handle)`, which already returns a Collection of related Entries; their titles are flattened into the `meta` bucket.

### 6.3 `App\Search\Types\UserSearchable`

```php
namespace App\Search\Types;

class UserSearchable extends \App\Search\AbstractSearchable
{
    protected string $handle = 'user';
    protected string $name = 'User';

    public function searchShouldIndex(Model $user): bool;
    public function searchFieldsFor(Model $user): Collection;        // pulls from Fieldable
    public function searchToDocument(Model $user, string $locale): array;
}
```

### 6.4 `App\Field\AbstractField` — extension

Each Field type declares its own indexability. This is the field-side counterpart of `AbstractSearchable` — one is "how is a model indexed", the other is "how does a field type contribute to that index".

```php
abstract class AbstractField
{
    // ...all existing methods unchanged...

    public function searchIsSearchable(): bool;             // default false
    public function searchDefaultWeight(): float;            // default 1.0
    public function searchDefaultBucket(): string;           // default 'body'
    public function searchToString(mixed $value): ?string;   // default null
}
```

Recommended per-type defaults:

| Type | Searchable | Bucket | Weight |
|---|---|---|---|
| `Text` | yes | body | 1.0 |
| `Textarea` | yes | body | 1.0 |
| `EmailAddress` | yes | meta | 0.5 |
| `Url` | yes | meta | 0.3 |
| `Telephone` | yes | meta | 0.3 |
| `Relationship` | yes | meta | 0.5 |
| `Number` | no | — | — |
| `Date` | no | — | — |
| `Boolean` | no | — | — |
| `ColorPicker` | no | — | — |

Non-searchable types are still queryable as facets via `search_payload` JSON; they just don't get FULLTEXT-tokenized.

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

    public function scopeSearchForTenant(Builder $q, ?int $tenantId): Builder;
    public function scopeSearchForLocale(Builder $q, string $locale): Builder;
    public function scopeSearchOfType(Builder $q, string $typeHandle): Builder;
    public function scopeSearchInGroup(Builder $q, int|string|EntryGroup $g): Builder;
    public function scopeSearchPublic(Builder $q): Builder;
    public function scopeSearchMatch(Builder $q, string $kw, array $weights): Builder;
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
    public function searchFieldPriorityFor(Field $f): array;     // ['weight' => ..., 'bucket' => ...]
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
    public function searchSetFieldWeight(EntryGroup $g, Field $f, float $weight, ?string $bucket = null): static;
    public function searchClearFieldWeight(EntryGroup $g, Field $f): static;
    public function searchResolvedFieldWeight(EntryGroup $g, Field $f): array;
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
  EntryObserver.php                   saved/deleted/restoring
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

Resolution order, **highest priority wins**:

```
1. Search\Collection field-priority override   (search_collection_field_priorities)
        ↓ no row
2. EntryGroup field-priority override           (entry_group_search_field_priorities)
        ↓ no row
3. Field instance override                      (fields.is_searchable + fields.search_weight)
        ↓ null
4. Field type default                           (AbstractField::searchDefault*())
```

Four levels. Mirrors how field validation, rendering, and storage already cascade through `Field` → `Field\Type` → concrete `AbstractField` subclass — the same mental model applies.

The cascade is **resolved at index time** and the resulting weight + bucket assignment is *baked into* `search_documents`. Query-time scoring does not re-walk the cascade — it reads the FULLTEXT columns. This avoids per-query joins against config tables and gives stable performance under load.

The cost is that *changes to the cascade trigger reindexing*. That's why every level in the cascade has an observer: a row added to `search_collection_field_priorities` queues a reindex of every Entry in every affected Group; a Field's `search_weight` change queues a reindex of every Group that uses that Field. This is acceptable because admin-driven config changes are rare relative to content edits.

## 8. Buckets and Weighting

Three buckets, three FULLTEXT columns, three weights.

- **`search_title`** — short, high-impact. Default weight `2.0`.
- **`search_body`** — primary content. Default weight `1.0`.
- **`search_meta`** — categories, related-entry titles, tags, secondary identifiers. Default weight `0.5`.

Query-time relevance is a single weighted MATCH:

```sql
ORDER BY
    MATCH(search_title) AGAINST(? IN BOOLEAN MODE) * :w_title +
    MATCH(search_body)  AGAINST(?)                * :w_body +
    MATCH(search_meta)  AGAINST(?)                * :w_meta
DESC
```

This is the only correct way to do per-bucket boosting in MySQL — concatenated content with literal delimiters does not survive tokenization, the delimiters become noise, and you lose the ability to weight differently across the segments.

Optional multipliers, applied after the weighted MATCH:

- **Freshness decay** — `1.0 + 0.5 * exp(-days_since_published / half_life)`, capped at `1.5x`. Off by default; configured per-Group.
- **Status multiplier** — configurable map: `published 1.0`, `featured 1.5`, `archived 0.3`, others `0.5`. Configured globally in `config/search.php` with optional per-Group override via JSON column.

Both multipliers are applied *in the database*, not in PHP — the QueryBuilder emits a `CASE` expression for status and an arithmetic expression for freshness. No N+1 PHP scoring loop.

## 9. Indexing Pipeline

Triggers — these are the *correct* hooks, the ones that catch real edits:

1. `Entry` `saved` / `deleted` / `restoring` → reindex the Entry.
2. `User` `saved` / `deleted` → reindex the User.
3. `FieldValue` `saved` / `deleted` → resolve the fieldable owner via morphTo, reindex it. *This is the most important hook* — almost all content edits flow through `FieldValue`, and an observer on `Entry` alone misses every one of them.
4. `EntryRelationship` `saved` / `deleted` → reindex the parent Entry (so `meta` reflects current relationships).
5. `Search\Config` `saved` (per-Group config) → reindex every Entry in the Group.
6. `Search\Collection` `saved` / pivot changes → invalidate `CollectionResolver` cache; collection priority changes also queue affected Group reindexes.
7. `Field` `is_searchable` or `search_weight` changed → queue reindex of every Group that uses the Field.
8. Status transition to non-public OR soft-delete → call `searchUnindex()` and remove documents.

All triggers default to queuing (`SEARCH_QUEUE=true`, configurable). Single-record reindex is idempotent: it computes the new document and `updateOrInsert`s on the unique `(searchable_id, searchable_type, locale)` key. Batch reindex chunks by ID range and is interrupt-safe — reruns resume cleanly.

Concurrency: a `searchable_id`-keyed Redis lock with a 30s TTL wraps the per-model indexer to prevent duplicate work when multiple jobs are queued for the same target. (The unique key already protects correctness; the lock is for efficiency.)

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

### Phase 0 — Spike (1–2 days)

Confirm MySQL FULLTEXT viability against current content shapes:

- Verify InnoDB FULLTEXT support; tune `innodb_ft_min_token_size` if 3-letter terms (`php`, `sql`) are needed.
- Build a throwaway 3-bucket FULLTEXT table, populate from current Entries + Field Values via a one-off script, measure relevance and latency on a representative query mix.
- **Decision gate**: continue with native MySQL FULLTEXT, or jump to Meilisearch from the start. Default recommendation: native FULLTEXT, defer Meilisearch to Phase 10.

### Phase 1 — Foundation (week 1)

A registry, a Document, a trait skeleton — no live indexing yet.

- Migrations: `search_types`, `search_documents`, `add_search_columns_to_fields_table`.
- Models: `Search\Type`, `Search\Document`.
- Abstract: `Search\AbstractSearchable`.
- Trait skeleton: `Searchable`.
- Seeder: `SearchTypeSeeder` registers `entry` and `user`.
- Tests: model relations, Document scopes, factories.

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
- Tests: indexed content lands in the right buckets; relational fields contribute related entry titles to `meta`; non-searchable fields are excluded.

**Done = `Search::entries()->searchIndex($entry)` writes a correct row.**

### Phase 4 — Observer pipeline (week 2–3)

Indexes stay in sync with live data — *every* edit path.

- `Observers\Search\EntryObserver`, `UserObserver`, `FieldValueObserver`, `EntryRelationshipObserver`.
- `Jobs\Search\ReindexModel`.
- Tests: editing a `FieldValue` updates the document; soft-delete unindexes; status change to non-public unindexes; `EntryRelationship` add/remove updates parent `meta`.

**Done = no edit path leaves the index stale.**

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

Per-Group field weights, buckets, and toggles via admin UI.

- Migrations: `entry_group_search_configs`, `entry_group_search_field_priorities`.
- Models: `Search\Config`, `Search\FieldPriority`.
- Trait: `HasSearchConfig` applied to `EntryGroup`.
- `Observers\Search\ConfigObserver` queues full-group reindex on config change.
- Indexer respects per-Group overrides.
- Admin UI: per-Group field-priority editor (mirrors FieldLayout admin UX).
- Tests: weight cascade resolution, bucket override, disabled-field exclusion, reindex-on-config-change.

**Done = admins can configure search per Entry Group without code.**

### Phase 7 — Collections (week 4–5)

Composable, multi-Group, multi-Type search scopes with priority overrides.

- Migrations: 4 collection tables.
- Models: `Search\Collection`, `Search\Collection\Group`, `Search\Collection\FieldPriority`, `Search\Collection\Type`.
- Trait: `HasSearchableGroups`.
- `Search\CollectionResolver` (cached, invalidated by `CollectionObserver`).
- `SearchService::collection()` + QueryBuilder `searchInCollection()`.
- Multi-type query merge (one query per searchable type, sorted by computed relevance × inter-type weight).
- Admin UI: collection CRUD, group attachment with sort order, Type attachment, weight override editor.
- Tests: cross-group weight isolation; multi-type result merge; locale filtering; collection vs group precedence.

**Done = the user-facing requirement for composable Collections is delivered.**

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

- Toggle: **searchable** yes/no (translates to `search_weight = 0` or row removal).
- Weight slider/input (`0.0` – `5.0`, step `0.25`).
- Bucket selector (`title` / `body` / `meta`).
- Inline indicator: "inherits from field type default" vs. "overridden here".

Plus group-level toggles: master kill-switch, freshness decay (with half-life input), default locale, status-multiplier overrides.

### 12.2 `/admin/search/collections`

Top-level Collection management, mirroring `/admin/entry-groups`.

Per-Collection edit screen has three tabs:

1. **General** — name, handle, description, locale, active flag.
2. **Sources** — attached Entry Groups (sortable by `sort_order`), attached Searchable Types (Entry, User), per-type weight.
3. **Field Priorities** — per-(Group, Field) override editor; defaults to "inherit" with an explicit "override" toggle. Disabled (greyed) for any field the Group itself has disabled, with hover text explaining why.

### 12.3 Field instance override

Two new inputs added to the existing Field edit screen: **searchable (override)** dropdown (`Inherit` / `Yes` / `No`) and **weight (override)** input.

## 13. Testing Plan

Pyramid by phase. Every phase ships with tests that cover its delta.

- **Unit** — every model, trait method, service method; every `AbstractField` subclass override; every `QueryBuilder` method; every `KeywordParser` allowlist edge case.
- **Feature** — full save → observer → index round-trip; edit → update → search hit; soft-delete → unindex; configuration cascade resolution.
- **Integration** — per-phase smoke tests against a seeded fixture (one EntryGroup, three Entries with mixed field types, three Users, one Collection composing multiple Groups).
- **Performance** — at end of Phase 5, baseline keyword query latency at 10K and 100K rows. Gate proceeding past Phase 7 on this number.

## 14. Open Decisions

These need a call before Phase 1 starts.

1. **MySQL FULLTEXT vs Meilisearch from day one.** Phase 0 spike informs this. Default recommendation: native FULLTEXT, defer Meilisearch to Phase 10. The Phase 10 swap is non-breaking because the model-side API doesn't change.
2. **Include `tenant_id` column now or later.** Recommended: include the column nullable in Phase 1 migrations; populate when tenancy lands. Cheaper than `ALTER TABLE` on what may eventually be a large table.
3. **Translatable fields.** If `spatie/laravel-translatable` is going to be turned on for Entry titles and text fields in the near term, build per-locale documents from Phase 1. If not, default-locale only and revisit in Phase 9.
4. **Status multipliers — global config or per-group config.** Recommended: global default in `config/search.php`, per-Group override via `entry_group_search_configs.search_status_multipliers` JSON. Keeps Phase 6 simple while leaving room for tuning.
5. **Whether `User` is searchable by default.** Users carry private data. Recommended: User searchability is opt-in via a `users.is_searchable` flag and gated by a permission check in QueryBuilder so that admin search ≠ public search.
6. **Concurrency model for reindexing.** With unique key on `(searchable_id, searchable_type, locale)`, `updateOrInsert` is correct. But duplicate work across queue workers is wasteful — recommend a `searchable_id`-keyed Redis lock with 30s TTL inside the Indexer.

## 15. Risks

- **Index bloat** — native FULLTEXT indexes grow `1.5x – 3x` source content. Phase 5 baseline measurements gate Phase 7+.
- **Reindex storms** — a Field weight change in a 1M-row group triggers a 1M-row reindex. Mitigated by chunked, queued, resumable batch jobs and admin-visible progress.
- **Status multiplier interactions** — if `archived = 0.3x` is too low, archived content effectively never surfaces. Configurable, with a "minimum visible relevance" threshold so 0.3x doesn't always rank below excluded.
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
