# Search System -- Implementation Plan (V2)

## The Core Idea

Move all weight decisions to index time. When a field element has weight 3, its
words appear three times in the stored keyword blob. MySQL FULLTEXT relevance is
driven by term frequency, so weighted ranking emerges at query time for free --
no JOINs, no per-document weight resolution, no complex scoring expressions.

Search configuration lives on `field_layout_tab_elements`, not on `Field`. A
`Field` is a global object; the same field can appear in a Blog layout, a User
layout, and a Category layout. The `TabElement` is the "field in context" junction
-- the right place to say "in this layout, this field is searchable at weight 3."

The Indexer is completely model-agnostic. Every Searchable model exposes three
methods; the Indexer calls them without knowing what type it is working with.
Adding a new Fieldable type to search means applying a trait and overriding one
to three methods -- no changes to the Indexer, jobs, or schema.

---

## 1. Data Model

### 1.1 `field_layout_tab_elements` additions

Search configuration sits here rather than on `fields`. This lets the same field
carry different searchability and weight depending on which layout it appears in.

```
field_layout_tab_elements (additions)
--------------------------------------------------
is_searchable    boolean default false
search_weight    tinyInteger unsigned default 1    range 1-10
```

No changes to the `fields` table.

### 1.2 `search_index`

One row per indexable model instance.

```
search_index
--------------------------------------------------
id
indexable_id        unsignedBigInteger
indexable_type      string              morph alias ('entry', 'user', 'category', 'media', ...)
owner_id            unsignedBigInteger nullable    generic scope; see note below
owner_type          string nullable                morph alias of the owner ('entry_group', 'category_group', ...)
subtype_id          unsignedBigInteger nullable    entry_type_id equivalent; null for non-Entry types
keywords            mediumText          FULLTEXT indexed
search_updated_at   timestamp
timestamps

unique  (indexable_id, indexable_type)
index   (owner_type, owner_id)
index   (owner_type, owner_id, subtype_id)
FULLTEXT (keywords)
```

`owner_id` / `owner_type` replace the previous `entry_group_id` / `entry_type_id`
columns with a generic pair that works for any Fieldable type:

| Indexable type | owner_type       | owner_id              | subtype_id        |
|----------------|------------------|-----------------------|-------------------|
| `entry`        | `entry_group`    | `entry_groups.id`     | `entry_types.id`  |
| `user`         | null             | null                  | null              |
| `category`     | `category_group` | `category_groups.id`  | null              |
| `media`        | `media_library`  | `media_libraries.id`  | null              |

### 1.3 `search_collections`

```
search_collections
--------------------------------------------------
id
name                string
handle              string unique
description         text nullable
sort_order          integer default 0
timestamps
```

### 1.4 `search_collection_scopes` (pivot)

Each row says "include this type of content, optionally within this owner scope,
optionally filtered to these subtypes." A collection with no type restriction
on `subtype_ids` includes all subtypes within the owner.

```
search_collection_scopes
--------------------------------------------------
id
search_collection_id    fk -> search_collections (cascadeOnDelete)
indexable_type          string          ('entry', 'user', 'category', 'media')
owner_id                nullable        null = include all instances of this type
owner_type              nullable
subtype_ids             json nullable   e.g. [3, 7] restricts to these entry_type ids
timestamps

unique (search_collection_id, indexable_type, owner_type, owner_id)
```

A collection covering "Blog entries + all Users + Press Release categories"
is three rows in this table.

### 1.5 `entry_groups` addition

```
entry_groups (addition)
--------------------------------------------------
search_settings   json nullable
```

Holds per-group weight overrides for Entry synthetic sources:

```json
{ "title_weight": 5, "handle_weight": 0, "category_weight": 3 }
```

Weight `0` excludes that source. Absent keys fall back to `config('search.weights.*')`.

---

## 2. The `Searchable` Trait

Applied to any Fieldable model. Provides the index relation, dispatch helpers, and
the three methods the Indexer calls. Models override only what differs from the
defaults.

```php
// App\Traits\Searchable

public function searchIndex(): MorphOne
{
    return $this->morphOne(SearchIndex::class, 'indexable');
}

/**
 * Dispatch an async reindex job. Call this after ALL related writes have committed.
 */
public function reindex(): void
{
    IndexModelJob::dispatch($this->getMorphClass(), $this->getKey())
        ->onQueue(config('search.queue', 'search'));
}

/**
 * Remove this model's search row. Call explicitly from service/repository delete paths.
 */
public function purgeSearchIndex(): void
{
    $this->searchIndex()->delete();
}

/**
 * Return the TabElement objects for this model's effective field layout,
 * in traversal order. The Indexer reads is_searchable and search_weight
 * from each element.
 *
 * Default: reads from $this->fieldLayout (single-layout models).
 * Entry overrides this to merge type + group layout elements.
 */
public function searchableElements(): Collection
{
    $this->loadMissing('fieldLayout.tabs.elements.field.fieldType');

    return $this->fieldLayout?->tabs->flatMap(fn($tab) => $tab->elements)
        ?? collect();
}

/**
 * Return synthetic text sources as [weight => text] pairs.
 * These are indexed regardless of field settings (title, handle, email, etc.)
 *
 * Default: no synthetic sources. Models override to add their own.
 */
public function searchableSyntheticText(): array
{
    return [];
}

/**
 * Return [owner_id, owner_type] for the search_index scope columns.
 * Default: [null, null] (no owner scope -- used for User and similar types).
 */
public function searchOwner(): array
{
    return [null, null];
}
```

### What each model overrides

**`Entry`** -- all three methods:

```php
public function searchableElements(): Collection
{
    $this->loadMissing([
        'entryType.fieldLayout.tabs.elements.field.fieldType',
        'entryGroup.fieldLayout.tabs.elements.field.fieldType',
    ]);

    $typeTabs  = $this->entryType->fieldLayout?->tabs ?? collect();
    $groupTabs = $this->entryGroup->fieldLayout?->tabs ?? collect();

    $typeFieldIds = $typeTabs
        ->flatMap(fn($t) => $t->elements->pluck('field.id'))
        ->filter()->all();

    $filteredGroupTabs = $groupTabs->map(function ($tab) use ($typeFieldIds) {
        $tab = clone $tab;
        $tab->setRelation(
            'elements',
            $tab->elements->reject(fn($el) => in_array($el->field?->id, $typeFieldIds))
        );
        return $tab;
    })->filter(fn($tab) => $tab->elements->isNotEmpty());

    return $typeTabs->concat($filteredGroupTabs)
        ->flatMap(fn($tab) => $tab->elements);
}

public function searchableSyntheticText(): array
{
    $s = $this->entryGroup->search_settings ?? [];

    return array_filter([
        ($s['title_weight']    ?? config('search.weights.title'))      => $this->title,
        ($s['handle_weight']   ?? config('search.weights.handle'))     => $this->handle,
        ($s['category_weight'] ?? config('search.weights.categories')) => $this->categoryNamesForIndex(),
    ], fn($text) => $text !== null && $text !== '');
}

public function searchOwner(): array
{
    return [$this->entry_group_id, 'entry_group'];
}

private function categoryNamesForIndex(): ?string
{
    $this->loadMissing('categories');
    $names = $this->categories->pluck('name')->filter()->implode(' ');
    return $names !== '' ? $names : null;
}
```

**`User`** -- synthetic text and no owner scope needed:

```php
public function searchableSyntheticText(): array
{
    return array_filter([
        config('search.weights.title')  => $this->name,
        config('search.weights.handle') => $this->email,
    ]);
}
// searchableElements() default works -- UserSchema::resolved() should be wired
// into $this->fieldLayout via the UserSchema singleton. If not, override here.
// searchOwner() default [null, null] is correct.
```

**`Category`** -- synthetic text and group owner:

```php
public function searchableSyntheticText(): array
{
    return [config('search.weights.title') => $this->name];
}

public function searchOwner(): array
{
    return [$this->category_group_id, 'category_group'];
}
```

**`Media`** -- similar pattern; override `searchableSyntheticText()` to return
filename, title, alt text, and `searchOwner()` to return the library.

No other changes required to add a new type. The Indexer never branches on model
class.

---

## 3. Field Text Extraction

Search configuration is on `TabElement`, but text extraction still needs to route
correctly for scalar vs. relational field storage.

### 3.1 `AbstractField::toSearchText()`

```php
/**
 * Return indexable text for this field's value on the given model, or null to skip.
 *
 * Default reads from field_values (scalar storage). Relational and custom types
 * override this.
 */
public function toSearchText(Model $model, Field $field): ?string
{
    $fv = $model->fieldValues->first(fn($v) => $v->field_id === $field->id);
    if (! $fv) {
        return null;
    }

    $raw = $fv->{$this->storageColumn()};
    if ($raw === null || $raw === '' || $raw === false) {
        return null;
    }

    $cast = $this->cast($raw);
    return is_string($cast) ? strip_tags($cast) : (string) $cast;
}
```

### 3.2 Type overrides

| Type           | Override behaviour |
|----------------|--------------------|
| `Relationship` | Read `entryRelationships` for this `field_id`, collect `relatedEntry->title`, join with space. |
| `Boolean`      | Return null (true/false tokens add noise). |
| `Date`         | Return null by default. |
| `ColorPicker`  | Return null. |
| `Json`         | Return null by default; override to extract relevant keys per field. |

### 3.3 Indexer call site

```php
// App\Search\Indexer::extractText(Model $model, Field $field): ?string
$instance = $field->fieldType?->instance();
return $instance ? $instance->toSearchText($model, $field) : null;
```

The Indexer never reads `field_values` or `entry_relationships` directly.

---

## 4. The Indexer

Completely model-agnostic. Requires only that the model uses `Searchable`.

```php
// App\Search\Indexer::index(Model $model): void

public function index(Model $model): void
{
    $segments = [];

    // Synthetic sources (title, handle, email, etc.)
    foreach ($model->searchableSyntheticText() as $weight => $text) {
        if ($text && (int)$weight > 0) {
            $segments[] = $this->repeat(strip_tags((string)$text), (int)$weight);
        }
    }

    // Field elements
    $elements = $model->searchableElements();
    $model->loadMissing(['fieldValues.field.fieldType', 'entryRelationships.relatedEntry']);

    foreach ($elements as $element) {
        if (! $element->is_searchable) {
            continue;
        }

        $field  = $element->field;
        $weight = (int) ($element->search_weight ?? config('search.default_field_weight', 1));

        if (! $field || ! $field->fieldType || $weight < 1) {
            continue;
        }

        $text = $this->extractText($model, $field);
        if ($text !== null) {
            $segments[] = $this->repeat($text, $weight);
        }
    }

    [$ownerId, $ownerType] = $model->searchOwner();

    SearchIndex::updateOrCreate(
        [
            'indexable_id'   => $model->getKey(),
            'indexable_type' => $model->getMorphClass(),
        ],
        [
            'owner_id'          => $ownerId,
            'owner_type'        => $ownerType,
            'subtype_id'        => $model->getAttribute('entry_type_id'),
            'keywords'          => implode(' ', $segments),
            'search_updated_at' => now(),
        ]
    );
}

private function repeat(string $text, int $times): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return implode(' ', array_fill(0, max(1, $times), $text));
}
```

---

## 5. Dispatch Points

The `Searchable` trait exposes `reindex()` and `purgeSearchIndex()`, but wires
no model observers. Dispatch is the responsibility of the service and repository
layer for every type, because write paths for all Fieldable models follow the same
pattern: the model row is saved first, then related data (fields, roles, categories)
is written after.

### 5.1 Entry -- `EntryRepository`

```php
// create() -- after $entryType->afterCreate()
$entry->reindex();
return $entry;

// applyData() -- after $typeObject->afterUpdate()
$entry->reindex();
return $entry->refresh();

// setFieldValue() -- at its end
$entry->reindex();

// delete()
$entry->purgeSearchIndex();
return (bool) $entry->delete();
```

### 5.2 User -- `UserService`

```php
// create() -- end of method
return tap($user->refresh(), fn($u) => $u->reindex());

// update() -- end of method
return tap($user->refresh(), fn($u) => $u->reindex());

// delete()
$user->purgeSearchIndex();
return (bool) $user->delete();
```

### 5.3 Category, Media, and future types

Same pattern: call `$model->reindex()` at the end of each write method in the
relevant service or repository, after all related data has been committed. Call
`$model->purgeSearchIndex()` in the delete path.

### 5.4 Owner-level cascade cleanup

When an owner (EntryGroup, CategoryGroup, etc.) is deleted, its members are
removed by a DB-level cascade that fires no Eloquent events on the child models.
An observer on the owner model handles cleanup before the cascade fires, using
the `owner_id` / `owner_type` columns already in the index:

```php
// App\Observers\EntryGroupObserver
public function deleting(EntryGroup $group): void
{
    SearchIndex::where('owner_type', 'entry_group')
               ->where('owner_id', $group->getKey())
               ->delete();
}

// App\Observers\CategoryGroupObserver
public function deleting(CategoryGroup $group): void
{
    SearchIndex::where('owner_type', 'category_group')
               ->where('owner_id', $group->getKey())
               ->delete();
}
```

---

## 6. Jobs

### 6.1 `IndexModelJob`

```php
public function handle(Indexer $indexer): void
{
    $modelClass = Relation::getMorphedModel($this->morphClass);

    if (! $modelClass || ! class_exists($modelClass)) {
        Log::warning('IndexModelJob: unresolvable morph alias', [
            'morph_class' => $this->morphClass,
            'model_id'    => $this->modelId,
        ]);
        $this->fail(new \RuntimeException("Unresolvable morph alias: {$this->morphClass}"));
        return;
    }

    $model = $modelClass::find($this->modelId);

    if (! $model) {
        SearchIndex::query()
            ->where('indexable_type', $this->morphClass)
            ->where('indexable_id', $this->modelId)
            ->delete();
        return;
    }

    $indexer->index($model);
}
```

Retries: 3, exponential backoff. Queue set via `->onQueue()` at dispatch time
in `Searchable::reindex()`.

### 6.2 `ReindexOwnerJob`

Replaces the Entry-specific `ReindexGroupJob`. Accepts any `(indexable_type,
owner_type, owner_id)` triple and chunks through the matching `search_index` rows
to re-dispatch `IndexModelJob` for each.

```php
public function handle(): void
{
    // Resolve model class from morph alias
    $modelClass = Relation::getMorphedModel($this->indexableType);

    $modelClass::query()
        ->when($this->ownerId, function ($q) use ($modelClass) {
            // Use the model's natural FK column (entry_group_id, category_group_id, etc.)
            // Each model registers this via a static searchOwnerKey() method.
            $q->where($modelClass::searchOwnerKey(), $this->ownerId);
        })
        ->select('id')
        ->chunkById($this->chunkSize, function ($chunk) {
            foreach ($chunk as $model) {
                IndexModelJob::dispatch($this->indexableType, $model->id)
                    ->onQueue(config('search.queue', 'search'));
            }
        });
}
```

`searchOwnerKey()` is a static method on `Searchable` (defaulting to `null`) that
Entry overrides to return `'entry_group_id'`, Category to `'category_group_id'`,
etc. `ReindexOwnerJob` uses it to scope the chunk query correctly.

### 6.3 `ReindexAllJob`

Iterates all registered Searchable types, retrieves all owners (or dispatches
directly for owner-less types like User), and fans out `ReindexOwnerJob` calls.

---

## 7. Collections

### 7.1 No field-weight overrides at the collection level

Weights are editorial configuration set on `TabElement` within each layout.
Collections are pure scoping constructs: "search these types, within these owners,
optionally filtered to these subtypes." No weight overrides, no nondeterminism.

### 7.2 Collection query

```php
// App\Search\Query::forCollection(SearchCollection $collection, string $term)

$term = static::normaliseTerm($term);
if ($term === '') {
    return collect();
}

$scopes = $collection->scopes; // eager-loaded scope rows

return SearchIndex::query()
    ->whereRaw('MATCH(keywords) AGAINST(? IN BOOLEAN MODE)', [$term])
    ->where(function ($query) use ($scopes) {
        foreach ($scopes as $scope) {
            $query->orWhere(function ($q) use ($scope) {
                $q->where('indexable_type', $scope->indexable_type);

                if ($scope->owner_id) {
                    $q->where('owner_type', $scope->owner_type)
                      ->where('owner_id', $scope->owner_id);
                }

                if (! empty($scope->subtype_ids)) {
                    $q->whereIn('subtype_id', $scope->subtype_ids);
                }
            });
        }
    })
    ->selectRaw(
        '*, MATCH(keywords) AGAINST(? IN BOOLEAN MODE) AS relevance',
        [$term]
    )
    ->orderByDesc('relevance')
    ->paginate($perPage);
```

The outer `where(function(...))` groups all scope branches so they AND with MATCH
rather than appending as independent top-level OR conditions.

---

## 8. Search Term Normalisation

### 8.1 Default: plain token search

```php
public static function normaliseTerm(string $raw): string
{
    // Strip all FULLTEXT boolean-mode operators: + - > < ( ) ~ * " @
    $stripped = preg_replace('/[+\-><()~*"@]+/', ' ', $raw);
    return trim(preg_replace('/\s+/', ' ', $stripped));
}
```

Operators are removed, not escaped. This is the correct default for user-facing
search. Wildcard and phrase queries are not available in this mode.

### 8.2 Enhanced mode (opt-in)

```php
public static function normaliseTermEnhanced(string $raw): string
{
    // Allow * (suffix wildcard) and " (phrase delimiter).
    $stripped = preg_replace('/[+\-><()~@]+/', ' ', $raw);

    // Strip unpaired trailing quote.
    if (substr_count($stripped, '"') % 2 !== 0) {
        $stripped = rtrim($stripped, '"');
    }

    return trim(preg_replace('/\s+/', ' ', $stripped));
}
```

Callers must explicitly use this method. The default is never implicitly promoted.

---

## 9. `config/search.php`

```php
return [

    'queue' => env('SEARCH_QUEUE', 'search'),

    // Weights for synthetic sources. Entry Groups can override via search_settings.
    'weights' => [
        'title'      => 5,
        'handle'     => 1,
        'categories' => 2,
    ],

    // Weight for a field element with no search_weight set.
    'default_field_weight' => 1,

    'reindex_chunk_size' => 200,

];
```

---

## 10. Migrations (in order)

1. Add `is_searchable`, `search_weight` to `field_layout_tab_elements`
2. Add `search_settings` (json nullable) to `entry_groups`
3. Create `search_index`
4. Create `search_collections`
5. Create `search_collection_scopes`

---

## 11. Delivery Phases

| Phase | Deliverable |
|-------|-------------|
| 1 | Migrations |
| 2 | `config/search.php`; `SearchIndex` model; `Searchable` trait with all three methods; `Entry`, `User`, `Category` overrides |
| 3 | `AbstractField::toSearchText()` + type overrides; `Indexer`; `IndexModelJob`; `ReindexOwnerJob`; `ReindexAllJob` |
| 4 | Dispatch wired in `EntryRepository`, `UserService`, and category/media write paths; owner observer cleanup; `search:reindex` Artisan command |
| 5 | `Query` builder with `normaliseTerm()` and `forCollection()`; end-to-end keyword search |
| 6 | `SearchCollection` + scopes model; collection-scoped search |
| 7 | Admin UI: element-level search settings in layout editor; Collection management |

---

## 12. Explicitly Out of Scope

- Faceted aggregation -- query source models directly.
- Typo tolerance / synonyms -- Meilisearch or Typesense can replace `Indexer` and
  `Query` later without touching anything else.
- Per-locale index -- add `locale` as a third unique key dimension when needed.
- Relevance explanation UI -- `search_updated_at` shows freshness; weights are
  visible in the layout editor.
