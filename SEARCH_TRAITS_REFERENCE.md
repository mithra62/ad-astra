# Search Layer Traits - Method Naming Reference

All methods in Search-related traits are prefixed with `Search` to avoid collisions with other traits and parent methods.

---

## 1. `Searchable` Trait (Entry, User)

```php
namespace App\Traits;

trait Searchable
{
    /**
     * Get the associated search index record
     */
    public function searchIndex()
    {
        return $this->morphOne($this->getSearchIndexClass(), 'searchable');
    }

    /**
     * Determine the search index class for this model
     */
    protected function getSearchIndexClass()
    {
        return match(class_basename($this)) {
            'Entry' => SearchEntryIndex::class,
            'User'  => SearchUserIndex::class,
            default => throw new InvalidArgumentException(
                "No search index defined for " . class_basename($this)
            ),
        };
    }

    /**
     * Queue this model for reindexing
     */
    public function queueSearchReindex()
    {
        dispatch(new SearchReindexJob($this))->onQueue('search');
    }

    /**
     * Immediately reindex this model
     */
    public function reindexNow()
    {
        app(SearchIndexService::class)->indexEntry($this);
    }

    /**
     * Scope: only searchable items
     * Usage: Entry::searchable()->get()
     */
    public function scopeSearchable($query)
    {
        return $query->has('searchIndex');
    }

    /**
     * Scope: items not yet indexed or stale
     * Usage: Entry::needsSearchReindexing()->get()
     */
    public function scopeNeedsSearchReindexing($query)
    {
        return $query->doesntHave('searchIndex')
            ->orWhere(function ($q) {
                $q->whereHas('searchIndex', fn($idx) =>
                    $idx->where('indexed_at', '<', now()->subDays(30))
                );
            });
    }

    /**
     * Check if this model is currently indexed
     */
    public function isSearchIndexed(): bool
    {
        return $this->searchIndex()->exists();
    }

    /**
     * Get the timestamp of when this was last indexed
     */
    public function getSearchIndexedAt(): ?Carbon
    {
        return $this->searchIndex?->indexed_at;
    }
}
```

**Usage:**
```php
$entry = Entry::find(1);
$entry->reindexNow();  // Immediate
$entry->queueSearchReindex();  // Queued

Entry::searchable()->get();  // Only indexed entries
Entry::needsSearchReindexing()->get();  // Unindexed or stale

if ($entry->isSearchIndexed()) {
    echo "Last indexed: " . $entry->getSearchIndexedAt();
}
```

---

## 2. `HasSearchConfig` Trait (EntryGroup)

```php
namespace App\Traits;

trait HasSearchConfig
{
    /**
     * Get the search configuration for this group
     */
    public function searchConfig()
    {
        return $this->hasOne(SearchConfig::class);
    }

    /**
     * Ensure a search config exists, creating default if needed
     */
    public function ensureSearchConfig(): SearchConfig
    {
        return $this->searchConfig
            ?? SearchConfig::createDefault($this);
    }

    /**
     * Get all searchable fields for this group
     */
    public function getSearchableFields()
    {
        return $this->ensureSearchConfig()
            ->searchFields()
            ->where('is_enabled', true)
            ->with('field')
            ->get();
    }

    /**
     * Enable a field for search in this group
     * Usage: $group->enableSearchField($field, 2.0)
     */
    public function enableSearchField(Field $field, float $weight = 1.0): static
    {
        $config = $this->ensureSearchConfig();

        SearchField::updateOrCreate(
            ['search_config_id' => $config->id, 'field_id' => $field->id],
            ['is_enabled' => true]
        );

        SearchFieldPriority::updateOrCreate(
            ['search_config_id' => $config->id, 'field_id' => $field->id],
            ['weight' => $weight]
        );

        return $this;
    }

    /**
     * Disable a field from search
     * Usage: $group->disableSearchField($field)
     */
    public function disableSearchField(Field $field): static
    {
        $this->ensureSearchConfig()
            ->searchFields()
            ->where('field_id', $field->id)
            ->update(['is_enabled' => false]);

        return $this;
    }

    /**
     * Set field weight/priority in this group
     * Usage: $group->setSearchFieldWeight($field, 2.0)
     */
    public function setSearchFieldWeight(Field $field, float $weight): static
    {
        $config = $this->ensureSearchConfig();

        SearchFieldPriority::updateOrCreate(
            ['search_config_id' => $config->id, 'field_id' => $field->id],
            ['weight' => $weight]
        );

        return $this;
    }

    /**
     * Enable freshness decay for this group
     * Usage: $group->enableSearchFreshnessDecay(30)
     */
    public function enableSearchFreshnessDecay(int $halfLifeDays = 30): static
    {
        $config = $this->ensureSearchConfig();
        $config->update([
            'apply_freshness_decay' => true,
            'freshness_half_life'   => $halfLifeDays,
        ]);

        return $this;
    }

    /**
     * Reindex all entries in this group
     * Usage: $group->reindexAllSearchEntries()
     */
    public function reindexAllSearchEntries(): int
    {
        return app(SearchIndexService::class)->reindexGroup($this);
    }

    /**
     * Queue reindexing of all entries in this group
     */
    public function queueSearchReindexAll(): static
    {
        dispatch(new SearchReindexEntryGroup($this))->onQueue('search');
        return $this;
    }

    /**
     * Get SearchCollections this group is assigned to
     * Usage: $group->getAssignedSearchCollections()
     */
    public function getAssignedSearchCollections()
    {
        return SearchCollection::whereHas('searchCollectionGroups', fn($q) =>
            $q->where('entry_group_id', $this->id)
        )->get();
    }

    /**
     * Check if this group is part of a SearchCollection
     */
    public function isInSearchCollection(SearchCollection $collection): bool
    {
        return SearchCollectionGroup::where('search_collection_id', $collection->id)
            ->where('entry_group_id', $this->id)
            ->exists();
    }

    /**
     * Get the count of searchable entries in this group
     */
    public function getSearchableEntryCount(): int
    {
        return $this->entries()
            ->has('searchIndex')
            ->count();
    }

    /**
     * Clear all search indexes for this group
     */
    public function clearSearchIndexes(): int
    {
        return SearchEntryIndex::where('entry_group_id', $this->id)->delete();
    }
}
```

**Usage:**
```php
$group = EntryGroup::where('handle', 'blog')->first();

// Fluent configuration
$group
    ->enableSearchField($titleField, 2.0)
    ->enableSearchField($bodyField, 1.0)
    ->disableSearchField($apiKeyField)
    ->enableSearchFreshnessDecay(30);

// Set individual weights
$group->setSearchFieldWeight($summaryField, 1.5);

// Reindexing
$group->reindexAllSearchEntries();  // Synchronous
$group->queueSearchReindexAll();    // Queued

// Check assignments
$collections = $group->getAssignedSearchCollections();
if ($group->isInSearchCollection($mainCollection)) {
    echo "This group is searchable via main collection";
}

// Stats
echo "Searchable entries: " . $group->getSearchableEntryCount();

// Maintenance
$group->clearSearchIndexes();  // Nuclear option
```

---

## 3. `HasSearchableGroups` Trait (SearchCollection)

```php
namespace App\Traits\Search;

trait HasSearchableGroups
{
    /**
     * Add an entry group to this SearchCollection
     * Usage: $collection->addSearchGroup($group, 1)
     */
    public function addSearchGroup(EntryGroup $group, int $sortOrder = 0): static
    {
        SearchCollectionGroup::updateOrCreate(
            ['search_collection_id' => $this->id, 'entry_group_id' => $group->id],
            ['sort_order' => $sortOrder]
        );

        return $this;
    }

    /**
     * Remove an entry group from this SearchCollection
     * Usage: $collection->removeSearchGroup($group)
     */
    public function removeSearchGroup(EntryGroup $group): static
    {
        $this->searchCollectionGroups()
            ->where('entry_group_id', $group->id)
            ->delete();

        return $this;
    }

    /**
     * Set field priority/weight override for this SearchCollection
     * Usage: $collection->setSearchPriorityFor($field, 2.5)
     */
    public function setSearchPriorityFor(Field $field, float $weight): static
    {
        SearchCollectionFieldPriority::updateOrCreate(
            ['search_collection_id' => $this->id, 'field_id' => $field->id],
            ['weight' => $weight]
        );

        return $this;
    }

    /**
     * Remove priority override for a field
     * Reverts to group defaults
     * Usage: $collection->removeSearchPriorityFor($field)
     */
    public function removeSearchPriorityFor(Field $field): static
    {
        $this->searchCollectionFieldPriorities()
            ->where('field_id', $field->id)
            ->delete();

        return $this;
    }

    /**
     * Get all assigned entry groups
     * Usage: $collection->getSearchGroups()
     */
    public function getSearchGroups()
    {
        return EntryGroup::whereHas('searchCollectionGroups', fn($q) =>
            $q->where('search_collection_id', $this->id)
        )
        ->orderBy('sort_order')
        ->get();
    }

    /**
     * Get assigned entry groups with their sort order
     */
    public function getSearchGroupsWithOrder()
    {
        return $this->searchCollectionGroups()
            ->with('entryGroup')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($pivot) => [
                'group'      => $pivot->entryGroup,
                'sort_order' => $pivot->sort_order,
            ]);
    }

    /**
     * Check if an entry group is assigned to this collection
     */
    public function hasSearchGroup(EntryGroup $group): bool
    {
        return $this->searchCollectionGroups()
            ->where('entry_group_id', $group->id)
            ->exists();
    }

    /**
     * Get the count of assigned groups
     */
    public function getSearchGroupCount(): int
    {
        return $this->searchCollectionGroups()->count();
    }

    /**
     * Get all priority overrides for this collection
     */
    public function getSearchPriorityOverrides()
    {
        return $this->searchCollectionFieldPriorities()
            ->with('field')
            ->get();
    }

    /**
     * Reorder assigned groups
     * Usage: $collection->reorderSearchGroups([3 => 1, 5 => 2, 7 => 3])
     */
    public function reorderSearchGroups(array $groupIdToOrder): static
    {
        foreach ($groupIdToOrder as $groupId => $order) {
            $this->searchCollectionGroups()
                ->where('entry_group_id', $groupId)
                ->update(['sort_order' => $order]);
        }

        return $this;
    }

    /**
     * Clear all assigned groups from this collection
     */
    public function clearSearchGroups(): int
    {
        return $this->searchCollectionGroups()->delete();
    }
}
```

**Usage:**
```php
$collection = SearchCollection::where('handle', 'site-search')->first();

// Fluent group assignment
$collection
    ->addSearchGroup($blogGroup, 1)
    ->addSearchGroup($articlesGroup, 2)
    ->addSearchGroup($pagesGroup, 3);

// Field priority overrides
$collection
    ->setSearchPriorityFor($summaryField, 2.5)
    ->setSearchPriorityFor($tagField, 0.8);

// Query methods
$groups = $collection->getSearchGroups();
$groupCount = $collection->getSearchGroupCount();

if ($collection->hasSearchGroup($blogGroup)) {
    echo "Blog is in this collection";
}

// Maintenance
$collection->reorderSearchGroups([3 => 1, 5 => 2, 7 => 3]);
$collection->removeSearchPriorityFor($tagField);  // Revert to defaults
$collection->removeSearchGroup($archivedGroup);

// Stats
$overrides = $collection->getSearchPriorityOverrides();
foreach ($overrides as $override) {
    echo "{$override->field->handle}: {$override->weight}x";
}

// Nuclear
$collection->clearSearchGroups();  // Remove all assigned groups
```

---

## Complete Method Reference

### Searchable Trait Methods
```
✓ searchIndex()                          // Relationship
✓ getSearchIndexClass()                  // Protected helper
✓ queueSearchReindex()                   // Queue for reindexing
✓ reindexNow()                           // Immediate reindex
✓ scopeSearchable()                      // Scope: where indexed
✓ scopeNeedsSearchReindexing()           // Scope: where not indexed/stale
✓ isSearchIndexed()                      // Boolean check
✓ getSearchIndexedAt()                   // Get timestamp
```

### HasSearchConfig Trait Methods
```
✓ searchConfig()                         // Relationship
✓ ensureSearchConfig()                   // Get or create
✓ getSearchableFields()                  // List enabled fields
✓ enableSearchField()                    // Enable + set weight
✓ disableSearchField()                   // Disable field
✓ setSearchFieldWeight()                 // Update weight
✓ enableSearchFreshnessDecay()           // Configure decay
✓ reindexAllSearchEntries()              // Sync reindex all
✓ queueSearchReindexAll()                // Queue reindex all
✓ getAssignedSearchCollections()         // Get collections
✓ isInSearchCollection()                 // Boolean check
✓ getSearchableEntryCount()              // Get count
✓ clearSearchIndexes()                   // Delete all indexes
```

### HasSearchableGroups Trait Methods
```
✓ addSearchGroup()                       // Assign group
✓ removeSearchGroup()                    // Unassign group
✓ setSearchPriorityFor()                 // Override weight
✓ removeSearchPriorityFor()              // Remove override
✓ getSearchGroups()                      // Get assigned groups
✓ getSearchGroupsWithOrder()             // Get with sort_order
✓ hasSearchGroup()                       // Boolean check
✓ getSearchGroupCount()                  // Count groups
✓ getSearchPriorityOverrides()           // Get weight overrides
✓ reorderSearchGroups()                  // Update sort order
✓ clearSearchGroups()                    // Delete all assignments
```

---

## Collision Avoidance Examples

Without the `Search` prefix, these would collide with common Laravel methods:

| ❌ Without Prefix | ✓ With Prefix | Reason |
|------------------|---------------|--------|
| `getGroups()` | `getSearchGroups()` | Too generic, conflicts with other relations |
| `addGroup()` | `addSearchGroup()` | Too generic, other traits might use this |
| `removeGroup()` | `removeSearchGroup()` | Too generic, generic remove operations |
| `setPriority()` | `setSearchPriorityFor()` | Priority is a broad term in Eloquent |
| `reindex()` | `reindexAllSearchEntries()` | Could conflict with other indexing |
| `clear()` | `clearSearchIndexes()` | Too generic, other cleanup operations |
| `isIndexed()` | `isSearchIndexed()` | Avoids conflict with other index types |

---

**All trait methods now consistently use `Search` prefix to ensure no collisions with parent classes, other traits, or Laravel's built-in methods.**
