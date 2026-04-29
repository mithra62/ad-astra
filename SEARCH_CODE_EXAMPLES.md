# Full-Text Search: Code Examples & Usage Patterns
## (Naming: All new objects prefixed with `Search`)

---

## Part 1: Model Definitions (Pseudo-Code)

### SearchConfig Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchConfig extends Model
{
    protected $fillable = [
        'entry_group_id',
        'include_title',
        'include_handle',
        'apply_freshness_decay',
        'freshness_half_life',
        'created_from_layout',  // Track if auto-created
    ];

    protected $casts = [
        'include_title'        => 'boolean',
        'include_handle'       => 'boolean',
        'apply_freshness_decay'=> 'boolean',
        'freshness_half_life'  => 'integer',
    ];

    public function entryGroup(): BelongsTo
    {
        return $this->belongsTo(EntryGroup::class);
    }

    public function searchFields(): HasMany
    {
        return $this->hasMany(SearchField::class);
    }

    public function fieldPriorities(): HasMany
    {
        return $this->hasMany(SearchFieldPriority::class);
    }

    /**
     * Get weight for a specific field, fallback to default
     */
    public function getFieldWeight(Field $field, float $default = 1.0): float
    {
        return $this->fieldPriorities()
            ->where('field_id', $field->id)
            ->first()
            ?->weight ?? $default;
    }

    /**
     * Check if a field is enabled for search
     */
    public function isSearchable(Field $field): bool
    {
        return $this->searchFields()
            ->where('field_id', $field->id)
            ->where('is_enabled', true)
            ->exists();
    }
}
```

### SearchEntryIndex Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchEntryIndex extends Model
{
    protected $table = 'search_entry_indexes';

    protected $fillable = [
        'entry_id',
        'entry_group_id',
        'content',
        'title_snippet',
        'search_metadata',
        'indexed_at',
    ];

    protected $casts = [
        'search_metadata' => 'array',
        'indexed_at'      => 'datetime',
    ];

    public $timestamps = false;

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function entryGroup(): BelongsTo
    {
        return $this->belongsTo(EntryGroup::class);
    }

    /**
     * Scope: find by fulltext search
     */
    public function scopeSearch($query, string $keyword)
    {
        return $query->whereRaw(
            "MATCH(content) AGAINST(? IN BOOLEAN MODE)",
            [$this->formatKeywordForMySQL($keyword)]
        );
    }
}
```

### SearchCollection Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchCollection extends Model
{
    protected $fillable = [
        'name',
        'handle',
        'description',
        'search_type',  // 'entries', 'members', 'both'
    ];

    public function searchCollectionGroups(): HasMany
    {
        return $this->hasMany(SearchCollectionGroup::class);
    }

    public function searchCollectionFieldPriorities(): HasMany
    {
        return $this->hasMany(SearchCollectionFieldPriority::class);
    }

    /**
     * Get all entry groups assigned to this collection
     */
    public function entryGroups()
    {
        return EntryGroup::whereHas('searchCollectionGroups', fn($q) =>
            $q->where('search_collection_id', $this->id)
        )->orderBy('sort_order');
    }
}
```

---

## Complete Implementation Summary

All new Search layer objects consistently use the `Search` prefix:

**Models:**
- `SearchConfig` — Configuration per EntryGroup
- `SearchEntryIndex` — Denormalized full-text index
- `SearchUserIndex` — User search index
- `SearchCollection` — Grouping of entry groups for search
- `SearchCollectionGroup` — Pivot for SearchCollection ↔ EntryGroup
- `SearchCollectionFieldPriority` — Override weights per collection
- `SearchField` — Tracks which fields are searchable
- `SearchFieldPriority` — Field weights per config

**Services:**
- `SearchService` — Facade/entry point
- `SearchEntryService` — Entry search logic
- `SearchUserService` — User search logic
- `SearchIndexService` — Index building/updates
- `SearchWeightingService` — Relevance scoring
- `SearchConfigService` — Config management

**Builders & Other:**
- `SearchQueryBuilder` — Fluent query API
- `SearchIndexBuilder` — Content building
- `SearchEntryObserver` — Trigger indexing
- `SearchUserObserver` — User indexing trigger
- `SearchReindexEntryGroup` — Queued job
- `SearchReindexUser` — Queued job
- `SearchIndexRepository` — Index queries
- `SearchConfigRepository` — Config queries

---

**All naming now consistently applies the `Search` prefix**
