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

The Indexer is completely model-agnostic. Every Searchable model implements a
small, explicit contract; the Indexer calls it without knowing the model type.
Adding a new Fieldable type to search means applying the trait and overriding the
relevant methods -- no changes to the Indexer, jobs, or schema.

---

## 1. Data Model

### 1.1 `field_layout_tab_elements` additions

```
field_layout_tab_elements (additions)
--------------------------------------------------
is_searchable    boolean default false
search_weight    tinyInteger unsigned default 1    range 1-10
```

No changes to the `fields` table. The same field carries different searchability
and weight depending on which layout's `TabElement` it appears in.

### 1.2 `search_index`

One row per indexable model instance.

```
search_index
--------------------------------------------------
id
indexable_id        unsignedBigInteger
indexable_type      string              morph alias ('entry', 'user', 'category', ...)
owner_id            unsignedBigInteger nullable
owner_type          string nullable                morph alias of the owning scope
subtype_id          unsignedBigInteger nullable    entry_type_id equivalent; null for non-Entry types
keywords            mediumText          FULLTEXT indexed
search_updated_at   timestamp
timestamps

unique  (indexable_id, indexable_type)
index   (owner_type, owner_id)
index   (owner_type, owner_id, subtype_id)
FULLTEXT (keywords)
```

`owner_id` / `owner_type` are nullable here because `search_index` has no unique
key across those columns; NULLs are safe. The collection scopes table uses sentinel
values instead -- see section 1.4.

| Indexable type | owner_type       | owner_id              | subtype_id       |
|----------------|------------------|-----------------------|------------------|
| `entry`        | `entry_group`    | `entry_groups.id`     | `entry_types.id` |
| `user`         | null             | null                  | null             |
| `category`     | `category_group` | `category_groups.id`  | null             |
| `media`        | `media_library`  | (future)              | null             |

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

MySQL permits multiple rows with NULL in unique-indexed columns, so nullable
`owner_type` / `owner_id` would allow duplicate global scopes (e.g. two rows
for `user` with no owner) to slip through. Use sentinel values instead:
`owner_type` is `NOT NULL DEFAULT ''` and `owner_id` is `NOT NULL DEFAULT 0`.
A User scope row stores `owner_type = ''`, `owner_id = 0`.

```
search_collection_scopes
--------------------------------------------------
id
search_collection_id    fk -> search_collections (cascadeOnDelete)
indexable_type          string NOT NULL
owner_type              string NOT NULL DEFAULT ''
owner_id                unsignedBigInteger NOT NULL DEFAULT 0
subtype_ids             json nullable       e.g. [3,7] restricts to these subtype IDs; null = all
timestamps

unique (search_collection_id, indexable_type, owner_type, owner_id)
```

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

Weight `0` excludes that source. Absent or null keys fall back to
`config('search.weights.*')`.

---

## 2. The `Searchable` Trait -- Full Contract

Applied to any Fieldable model. All five methods have safe defaults; models
override only what differs.

```php
// App\Traits\Searchable

// ------------------------------------------------------------------
// Index relation and dispatch helpers
// ------------------------------------------------------------------

public function searchIndex(): MorphOne
{
    return $this->morphOne(SearchIndex::class, 'indexable');
}

public function reindex(): void
{
    IndexModelJob::dispatch($this->getMorphClass(), $this->getKey())
        ->onQueue(config('search.queue', 'search'));
}

public function purgeSearchIndex(): void
{
    $this->searchIndex()->delete();
}

// ------------------------------------------------------------------
// Contract methods -- implement or override in each model
// ------------------------------------------------------------------

/**
 * The FieldLayout whose TabElements carry this model's search configuration.
 *
 * Override this for single-layout models (User, Category, future Media).
 * Entry overrides searchableElements() directly because it merges two layouts.
 *
 * Default: null (no fields indexed -- safe for models not yet configured).
 */
public function searchableLayout(): ?FieldLayout
{
    return null;
}

/**
 * The TabElements to traverse when building the keyword blob.
 * Each element must have is_searchable (bool) and search_weight (int) columns.
 *
 * Default: reads from searchableLayout(). Entry overrides this directly.
 */
public function searchableElements(): Collection
{
    $layout = $this->searchableLayout();
    if (! $layout) {
        return collect();
    }

    $layout->loadMissing('tabs.elements.field.fieldType');

    return $layout->tabs->flatMap(fn($tab) => $tab->elements);
}

/**
 * Non-field text sources to include regardless of field settings.
 * Returns a list of ['weight' => int, 'text' => string] pairs.
 *
 * Using a list (not a keyed array) avoids silent key collisions when
 * two sources resolve to the same weight.
 *
 * Default: no synthetic sources.
 */
public function searchableSyntheticSources(): array
{
    return [];
}

/**
 * The [owner_id, owner_type] pair to store in search_index.
 * Return [null, null] for models with no owner scope (e.g. User).
 */
public function searchOwner(): array
{
    return [null, null];
}

/**
 * Subtype ID to store in search_index.subtype_id.
 * Return null for models that have no subtype concept.
 */
public function searchSubtypeId(): ?int
{
    return null;
}

/**
 * The model's DB column name that holds the owner foreign key.
 * Used by ReindexOwnerJob to scope chunk queries correctly.
 * Return null for models with no owner scope (e.g. User).
 */
public static function searchOwnerKey(): ?string
{
    return null;
}
```

### Model implementations

**`Entry`**

```php
// searchableLayout() not used -- searchableElements() is overridden directly.

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

public function searchableSyntheticSources(): array
{
    $s = $this->entryGroup->search_settings ?? [];

    $sources = [];

    if (($this->title ?? '') !== '') {
        $sources[] = [
            'weight' => (int) ($s['title_weight'] ?? config('search.weights.title', 5)),
            'text'   => $this->title,
        ];
    }

    if (($this->handle ?? '') !== '' && ($s['handle_weight'] ?? config('search.weights.handle', 1)) > 0) {
        $sources[] = [
            'weight' => (int) ($s['handle_weight'] ?? config('search.weights.handle', 1)),
            'text'   => $this->handle,
        ];
    }

    $this->loadMissing('categories');
    $categoryText = $this->categories->pluck('name')->filter()->implode(' ');
    if ($categoryText !== '' && ($s['category_weight'] ?? config('search.weights.categories', 2)) > 0) {
        $sources[] = [
            'weight' => (int) ($s['category_weight'] ?? config('search.weights.categories', 2)),
            'text'   => $categoryText,
        ];
    }

    return $sources;
}

public function searchOwner(): array
{
    return [$this->entry_group_id, 'entry_group'];
}

public function searchSubtypeId(): ?int
{
    return $this->entry_type_id;
}

public static function searchOwnerKey(): ?string
{
    return 'entry_group_id';
}
```

**`User`**

```php
public function searchableLayout(): ?FieldLayout
{
    return UserSchema::resolved()->fieldLayout;
}

public function searchableSyntheticSources(): array
{
    return [
        ['weight' => config('search.weights.title', 5),  'text' => $this->name],
        ['weight' => config('search.weights.handle', 1), 'text' => $this->email],
    ];
}

// searchOwner() default [null, null] is correct.
// searchSubtypeId() default null is correct.
// searchOwnerKey() default null is correct.
```

**`Category`**

```php
public function searchableLayout(): ?FieldLayout
{
    $this->loadMissing('group.fieldLayout.tabs.elements.field.fieldType');
    return $this->group?->fieldLayout;
}

public function searchableSyntheticSources(): array
{
    return [
        ['weight' => config('search.weights.title', 5), 'text' => $this->name],
    ];
}

public function searchOwner(): array
{
    return [$this->group_id, 'category_group'];
}

public static function searchOwnerKey(): ?string
{
    return 'group_id';
}
```

**Future Media**

When Media gains a field layout, adding it to search is:
1. Apply `Searchable` trait.
2. Override `searchableLayout()`, `searchableSyntheticSources()`, `searchOwner()`,
   and `searchOwnerKey()`.
3. Wire `reindex()` and `purgeSearchIndex()` in the Media write/delete paths.

No schema changes, no Indexer changes, no new jobs.

---

## 3. Field Text Extraction

The Indexer uses the existing `Fieldable` / `FieldValue` mechanism. It does not
read storage columns directly or route by field type. The flow is:

1. `$model->fieldValues` is eager-loaded with `field.fieldType`.
2. For each searchable element, the Indexer finds the matching `FieldValue` by
   `field_id` and calls `$fv->resolvedValue()`. This delegates to
   `$fieldType->instance()->storageColumn()` and the existing cast -- the same
   path every other part of the application uses.
3. The resolved value is passed to `toSearchText()` on the field type instance,
   which decides whether it contributes a string to the index.

### 3.1 `AbstractField::toSearchText()`

```php
/**
 * Convert an already-resolved field value to indexable text.
 *
 * Receives the output of FieldValue::resolvedValue() -- already cast to the
 * PHP type for this field (string, int, float, bool, Carbon, array, null).
 *
 * Return a string to include in the keyword blob, or null to skip this field.
 * Default: stringify and strip HTML. Types that produce no useful search text
 * override to return null.
 */
public function toSearchText(mixed $resolvedValue): ?string
{
    if ($resolvedValue === null || $resolvedValue === '' || $resolvedValue === false) {
        return null;
    }

    $text = is_string($resolvedValue) ? $resolvedValue : (string) $resolvedValue;
    $text = strip_tags($text);

    return $text !== '' ? $text : null;
}
```

### 3.2 Type overrides

Only types that should contribute nothing to the index need to override:

| Type           | Override |
|----------------|----------|
| `Boolean`      | `return null;` -- true/false tokens add noise. |
| `Date`         | `return null;` -- date strings rarely help FULLTEXT ranking. |
| `ColorPicker`  | `return null;` -- hex values are meaningless tokens. |

All text-bearing types (`Text`, `Textarea`, `EmailAddress`, `Telephone`, `Url`,
etc.) use the default implementation and need no override. `Relationship` fields
are not indexed at this stage.

---

## 4. The Indexer

Completely model-agnostic. Calls the five contract methods and nothing else.

```php
// App\Search\Indexer::index(Model $model): void

public function index(Model $model): void
{
    $segments = [];

    // Synthetic sources
    foreach ($model->searchableSyntheticSources() as $source) {
        $weight = (int) ($source['weight'] ?? 0);
        $text   = trim((string) ($source['text'] ?? ''));
        if ($text !== '' && $weight > 0) {
            $segments[] = $this->repeat(strip_tags($text), $weight);
        }
    }

    // Field elements
    $elements = $model->searchableElements();
    $model->loadMissing('fieldValues.field.fieldType');

    foreach ($elements as $element) {
        if (! $element->is_searchable) {
            continue;
        }

        $field  = $element->field;
        $weight = (int) ($element->search_weight ?? config('search.default_field_weight', 1));

        if (! $field || ! $field->fieldType || $weight < 1) {
            continue;
        }

        // Use the existing FieldValue resolution path -- same mechanism as
        // everywhere else in the application.
        $fv   = $model->fieldValues->first(fn($v) => $v->field_id === $field->id);
        $text = $field->fieldType->instance()->toSearchText($fv?->resolvedValue());

        if ($text !== null && $text !== '') {
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
            'subtype_id'        => $model->searchSubtypeId(),
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

## 5. Jobs

### 5.1 `IndexModelJob`

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

Retries: 3, exponential backoff. Queue set via `->onQueue()` in `Searchable::reindex()`.

### 5.2 `ReindexOwnerJob`

```php
public function __construct(
    public readonly string  $morphClass,
    public readonly ?int    $ownerId,     // null = reindex all of this type (e.g. all Users)
    public readonly int     $chunkSize = 200,
) {}

public function handle(): void
{
    $modelClass = Relation::getMorphedModel($this->morphClass);

    if (! $modelClass || ! class_exists($modelClass)) {
        $this->fail(new \RuntimeException("Unresolvable morph alias: {$this->morphClass}"));
        return;
    }

    $ownerKey = $modelClass::searchOwnerKey();

    if ($this->ownerId !== null && $ownerKey === null) {
        $this->fail(new \RuntimeException(
            "{$modelClass} has no searchOwnerKey() but ReindexOwnerJob received owner_id={$this->ownerId}"
        ));
        return;
    }

    $modelClass::query()
        ->when($this->ownerId !== null, fn($q) => $q->where($ownerKey, $this->ownerId))
        ->select('id')
        ->chunkById($this->chunkSize, function ($chunk) {
            foreach ($chunk as $model) {
                IndexModelJob::dispatch($this->morphClass, $model->id)
                    ->onQueue(config('search.queue', 'search'));
            }
        });
}
```

### 5.3 `ReindexAllJob`

Dispatches one `ReindexOwnerJob` per registered Searchable type. For owner-scoped
types (Entry, Category) it fans out one job per owner (one per EntryGroup, one per
CategoryGroup). For owner-less types (User) it dispatches a single job with
`ownerId = null`.

### 5.4 Owner-level cascade cleanup

Owner models (EntryGroup, CategoryGroup) get observers that clean up `search_index`
before the DB cascade removes their members:

```php
// App\Observers\EntryGroupObserver
public function deleting(EntryGroup $group): void
{
    SearchIndex::where('owner_type', 'entry_group')
               ->where('owner_id', $group->getKey())
               ->delete();
}

// App\Observers\CategoryGroupObserver  (add when Category becomes Searchable)
public function deleting(CategoryGroup $group): void
{
    SearchIndex::where('owner_type', 'category_group')
               ->where('owner_id', $group->getKey())
               ->delete();
}
```

---

## 6. Dispatch Points

No model observers. Dispatch is explicit from service and repository layer.

### 6.1 Entry -- `EntryRepository`

```php
// create()   -- after $entryType->afterCreate()
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

### 6.2 User -- `UserService`

```php
// create()
return tap($user->refresh(), fn($u) => $u->reindex());

// update()
return tap($user->refresh(), fn($u) => $u->reindex());

// delete()
$user->purgeSearchIndex();
return (bool) $user->delete();
```

### 6.3 Category and future types

Same pattern: `reindex()` at the end of each write method after all related data
has been committed; `purgeSearchIndex()` in the delete path.

---

## 7. Collections

### 7.1 No field-weight overrides at collection level

Weights are set on `TabElement` within each layout -- one authoritative setting
per field per context. Collections are pure scoping constructs.

### 7.2 Collection query

```php
// App\Search\Query::forCollection(SearchCollection $collection, string $term)

$term = static::normaliseTerm($term);
if ($term === '') {
    return collect();
}

$scopes = $collection->scopes; // eager-loaded

return SearchIndex::query()
    ->whereRaw('MATCH(keywords) AGAINST(? IN BOOLEAN MODE)', [$term])
    ->where(function ($query) use ($scopes) {
        foreach ($scopes as $scope) {
            $query->orWhere(function ($q) use ($scope) {
                $q->where('indexable_type', $scope->indexable_type);

                // owner_id 0 and owner_type '' are sentinels meaning "no owner"
                if ($scope->owner_id > 0) {
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

The outer `where(function(...))` groups all scope branches so they AND with MATCH.

---

## 8. Search Term Normalisation

### 8.1 Default: plain token search

```php
public static function normaliseTerm(string $raw): string
{
    // Remove all FULLTEXT boolean-mode operators.
    $stripped = preg_replace('/[+\-><()~*"@]+/', ' ', $raw);
    return trim(preg_replace('/\s+/', ' ', $stripped));
}
```

Operators are removed, not escaped. No wildcard or phrase support in this mode.

### 8.2 Enhanced mode (opt-in)

```php
public static function normaliseTermEnhanced(string $raw): string
{
    // Allow * (suffix wildcard) and paired " (phrase delimiter).
    $stripped = preg_replace('/[+\-><()~@]+/', ' ', $raw);

    if (substr_count($stripped, '"') % 2 !== 0) {
        $stripped = rtrim($stripped, '"');
    }

    return trim(preg_replace('/\s+/', ' ', $stripped));
}
```

Call sites must opt in explicitly.

---

## 9. `config/search.php`

```php
return [

    'queue' => env('SEARCH_QUEUE', 'search'),

    'weights' => [
        'title'      => 5,
        'handle'     => 1,
        'categories' => 2,
    ],

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
| 2 | `config/search.php`; `SearchIndex` model; `Searchable` trait with full contract; `Entry`, `User`, `Category` implementations |
| 3 | `AbstractField::toSearchText()` + type overrides; `Indexer`; `IndexModelJob`; `ReindexOwnerJob`; `ReindexAllJob` |
| 4 | Dispatch wired in `EntryRepository`, `UserService`, category write paths; owner observer cleanup; `search:reindex` Artisan command |
| 5 | `Query` builder with `normaliseTerm()` and `forCollection()`; end-to-end keyword search |
| 6 | `SearchCollection` + scopes model; collection-scoped search |
| 7 | Admin UI: element-level search settings in layout editor; Collection management |

---

## 12. Explicitly Out of Scope

- **Media** -- the contract already accommodates it. When Media gains a field layout,
  adding it to search is: apply trait, implement five methods, wire dispatch. No
  schema or Indexer changes required.
- **Faceted aggregation** -- query source models directly.
- **Typo tolerance / synonyms** -- Meilisearch or Typesense can replace `Indexer`
  and `Query` later without touching anything else.
- **Per-locale index** -- add `locale` as a third unique key dimension when needed.
