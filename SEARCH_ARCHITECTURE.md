# Full-Text Search Architecture
## Comprehensive Design Document (Naming: All new objects prefixed with `Search`)

---

## Part 1: Class Map & Object Model

### A. Models & Relationships

```
Entry (existing, add scope)
├── entryGroup: BelongsTo
├── entryType: BelongsTo
├── fieldValues: MorphMany → FieldValue
├── entryRelationships: HasMany → EntryRelationship
├── searchIndex: HasOne → SearchEntryIndex (NEW)
└── searchCollections: HasMany → SearchCollectionGroup (through SearchCollection)

EntryGroup (existing, extended)
├── entries: HasMany
├── fieldLayout: BelongsTo
├── searchConfig: HasOne → SearchConfig (NEW)
└── searchCollections: HasMany → SearchCollectionGroup

SearchConfig (NEW)
├── entryGroup: BelongsTo
├── searchFields: HasMany → SearchField (NEW)
├── fieldPriorities: HasMany → SearchFieldPriority (NEW)
└── indexed_at: timestamp

SearchField (NEW)
├── searchConfig: BelongsTo
├── field: BelongsTo
└── is_enabled: boolean

SearchFieldPriority (NEW)
├── searchConfig: BelongsTo
├── field: BelongsTo
└── weight: float (0.5–2.0)

SearchEntryIndex (NEW)
├── entry: BelongsTo
├── entryGroup: BelongsTo
├── content: text (full-text indexed)
├── title_snippet: varchar(255)
├── indexed_at: timestamp
└── search_metadata: json

SearchCollection (NEW)
├── name: string
├── handle: string
├── search_type: enum('entries', 'members', 'both')
├── searchCollectionGroups: HasMany → SearchCollectionGroup
└── searchCollectionFieldPriorities: HasMany → SearchCollectionFieldPriority

SearchCollectionGroup (pivot, NEW)
├── searchCollection: BelongsTo
├── entryGroup: BelongsTo
├── sort_order: int

SearchCollectionFieldPriority (NEW)
├── searchCollection: BelongsTo
├── field: BelongsTo
├── weight: float (overrides SearchConfig weight)

User (existing, add trait + scope)
├── searchIndex: HasOne → SearchUserIndex (NEW)
└── searchConfig: HasOne → SearchConfig (morphable, NEW)

SearchUserIndex (NEW)
├── user: BelongsTo
├── content: text
├── indexed_at: timestamp
├── search_metadata: json
└── search_type: enum('user')
```

### B. Class Hierarchy

```
Services/
├── SearchService.php (facade, delegates to specific services)
├── SearchEntryService.php (handles Entry search)
├── SearchUserService.php (handles User search)
├── SearchIndexService.php (builds/updates indexes)
├── SearchWeightingService.php (calculates relevance)
└── SearchConfigService.php (manage configs)

Repositories/
├── SearchIndexRepository.php (CRUD on search indexes)
└── SearchConfigRepository.php (CRUD on search configs)

Builders/
├── SearchQueryBuilder.php (fluent search API)
└── SearchIndexBuilder.php (index construction logic)

Jobs/
├── SearchReindexEntryGroup.php (queued reindex)
└── SearchReindexUser.php (queued reindex)

Traits/
└── Searchable.php (added to Entry, User; provides search() scope)

Models/
├── SearchConfig.php
├── SearchEntryIndex.php
├── SearchUserIndex.php
├── SearchCollection.php
├── SearchCollectionGroup.php
├── SearchCollectionFieldPriority.php
├── SearchField.php
├── SearchFieldPriority.php
└── (plus database lookup models)

Observers/
├── SearchEntryObserver.php (NEW, replaces EntryObserver extension)
└── SearchUserObserver.php (NEW)

Contracts/
├── SearchableModel.php (interface)
└── SearchProvider.php (interface)
```

---

## Part 2: Field Weighting Algorithm

### A. Weight Calculation Hierarchy

```
Final Relevance Score = 
  (BM25 Score) × 
  (Field Weight Factor) × 
  (Collection Weight Override) ×
  (Freshness Decay) ×
  (Status Multiplier)
```

### B. Detailed Breakdown

#### 1. **BM25 Score** (baseline from MySQL MATCH)
MySQL NATURAL LANGUAGE MODE gives baseline relevance. For BOOLEAN MODE, we apply BM25 ourselves:

```
BM25(term, doc) = IDF(term) × (
  (k1 + 1) × TF(term, doc)
) / (
  k1 × (1 - b + b × (docLen / avgDocLen)) + TF(term, doc)
)

where:
  k1 = 1.2   (term frequency saturation, typical: 1.2–2.0)
  b = 0.75   (field length normalization, typical: 0.7–0.9)
  IDF = log(N / (n + 1))  where N = total docs, n = docs containing term
```

**Application**: 
- For each field in `search_entry_indexes.content`, calculate BM25
- Store in indexed_at or recompute on query

#### 2. **Field Weight Factor**

From `SearchFieldPriority` or `SearchConfig.fieldPriorities`:

```php
$fieldWeights = [
    'title'       => 2.0,    // Most important
    'summary'     => 1.5,    // Secondary
    'body'        => 1.0,    // Baseline
    'tags'        => 0.8,    // Lower priority
    'metadata'    => 0.5,    // Minimal
];

// If term matches in `title`, multiply by 2.0
// If term matches in `body`, multiply by 1.0
```

**How it works**:
- Store field metadata in `SearchEntryIndex.search_metadata` (JSON)
  ```json
  {
    "fields": {
      "title": {"weight": 2.0, "matched_terms": ["laravel", "testing"]},
      "body": {"weight": 1.0, "matched_terms": ["laravel"]}
    }
  }
  ```
- Query calculates: `BM25 × weights[matched_field]`

#### 3. **Collection Weight Override**

If searching within a SearchCollection, apply override:

```php
// SearchCollection 'featured-articles' gives different weights
SearchCollectionFieldPriority::where('search_collection_id', $collectionId)
  ->where('field_id', $fieldId)
  ->first()?->weight
  ?? 
// Fallback to group default
SearchFieldPriority::where('search_config_id', $configId)
  ->where('field_id', $fieldId)
  ->first()?->weight
  ?? 
// Fallback to default
1.0;
```

#### 4. **Freshness Decay** (Optional)

For time-sensitive content (blog posts, news):

```
freshnessFactor = 1.0 + (0.5 × exp(-daysSincePublish / 30))

// Newly published entries get +50% boost
// Older entries decay gradually
// Max boost: 50% for very recent
// Min boost: ~0% after 90 days
```

Configure per SearchConfig:
```php
$config->apply_freshness_decay = true;
$config->freshness_half_life = 30; // days
```

#### 5. **Status Multiplier**

```php
$statusMultiplier = match($entry->status_handle) {
    'published'  => 1.0,    // Full weight
    'featured'   => 1.5,    // Boost featured
    'archived'   => 0.3,    // Deemphasize
    'draft'      => 0.0,    // Exclude (unless include_drafts=true)
    default      => 0.5,
};
```

### C. Complete Scoring Example

```
Entry: "Laravel Testing Best Practices"
Searched: "laravel testing"

BM25 Score (raw):            2.5
├─ Title match (2.0×):       2.5 × 2.0 = 5.0
├─ Field weight applied
├─ IDF('laravel'):           0.9
├─ IDF('testing'):           1.2
└─ Result: 5.0

Collection override (featured-blog):
├─ title weight: 2.0 (no override)
├─ Applied: 5.0 × 1.0 = 5.0

Freshness (published 5 days ago):
├─ Factor: 1.0 + 0.5 × exp(-5/30) = 1.33
├─ Applied: 5.0 × 1.33 = 6.65

Status (published):
├─ Multiplier: 1.0
├─ Applied: 6.65 × 1.0 = 6.65

━━━━━━━━━━━━━━━━━━━━━━━━
FINAL SCORE: 6.65
```

---

## Part 3: Index Building Lifecycle

### A. Event-Driven Indexing

```
┌─────────────────────────────────────────────────────┐
│ Entry is Created / Updated                          │
└──────────────────┬──────────────────────────────────┘
                   │
                   ▼
        ┌────────────────────────┐
        │ SearchEntryObserver::   │
        │ updated() / created()   │
        └────────────┬────────────┘
                     │
                     ▼
      ┌──────────────────────────────────┐
      │ SearchIndexService::             │
      │ indexEntry($entry)               │
      │ (OR queue job if slow)           │
      └──────────────┬───────────────────┘
                     │
         ┌───────────┴───────────────┐
         │                           │
         ▼                           ▼
    ┌─────────────┐          ┌──────────────┐
    │ Extract     │          │ Fetch Search │
    │ Core Data   │          │ Config from  │
    │             │          │ EntryGroup   │
    │ - title     │          │              │
    │ - handle    │          │ Which fields │
    │ - summary   │          │ are enabled? │
    └──────┬──────┘          │ What weights?│
           │                 └──────┬───────┘
           │                        │
           └────────────┬───────────┘
                        │
                        ▼
        ┌────────────────────────────────┐
        │ SearchIndexBuilder::            │
        │ buildSearchContent(             │
        │   $entry,                       │
        │   $enabledFields,               │
        │   $weights                      │
        │ )                               │
        └────────────┬───────────────────┘
                     │
         ┌───────────┴───────────────────┐
         │                               │
         ▼                               ▼
    ┌──────────────┐          ┌─────────────────┐
    │ Resolve each │          │ Skip disabled   │
    │ field's      │          │ fields (e.g.,   │
    │ value        │          │ password, api)  │
    │              │          │                 │
    │ - fieldValue │          │ Build final     │
    │ - field type │          │ $content string │
    │ - metadata   │          │ with delimiters │
    └──────┬───────┘          └────────┬────────┘
           │                           │
           └───────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────────┐
        │ Concatenate with Field Delimiters│
        │                                  │
        │ "|||TITLE|||" . "Laravel Testing"│
        │ "|||BODY|||" . "Best practices..."│
        │ "|||TAGS|||" . "php,testing"     │
        └────────────┬─────────────────────┘
                     │
                     ▼
        ┌──────────────────────────────────┐
        │ Create/Update SearchEntryIndex    │
        │ row:                             │
        │                                  │
        │ - entry_id                       │
        │ - entry_group_id                 │
        │ - content (full text)            │
        │ - title_snippet                  │
        │ - search_metadata (JSON)         │
        │ - indexed_at (NOW)               │
        └────────────┬─────────────────────┘
                     │
                     ▼
        ┌──────────────────────────────────┐
        │ ✓ Index Complete                 │
        │                                  │
        │ SearchEntryIndex now searchable  │
        │ via FULLTEXT index               │
        └──────────────────────────────────┘
```

### B. Lifecycle Details

#### Step 1: Observer Trigger
```php
// app/Observers/SearchEntryObserver.php
public function created(Entry $entry)
{
    // Immediately or queue
    SearchIndexService::indexEntry($entry);
    // OR
    SearchReindexEntryGroup::dispatch($entry);
}

public function updated(Entry $entry)
{
    // Only reindex if field_layout changed or searchable fields updated
    if ($entry->isDirty(['entry_type_id', 'entry_group_id'])) {
        SearchIndexService::indexEntry($entry);
    }
}
```

#### Step 2: Config Resolution
```php
// SearchIndexService::indexEntry()
$entry->load('entryGroup.searchConfig.searchFields');

$config = $entry->entryGroup->searchConfig;

if (!$config) {
    // Create default if none exists
    $config = SearchConfig::createDefault($entry->entryGroup);
}

$enabledFields = $config->searchFields()
    ->where('is_enabled', true)
    ->with('field')
    ->get();
```

#### Step 3: Content Building
```php
// SearchIndexBuilder::buildSearchContent()
$content = '';
$metadata = [];

foreach ($enabledFields as $searchField) {
    $field = $searchField->field;
    $fieldHandle = $field->handle;
    
    // Resolve value
    $value = $entry->field($fieldHandle);
    
    if ($value === null) continue;
    
    // Get weight
    $weight = $field->weight ?? 1.0;
    
    // Convert value to text
    $textValue = $this->fieldTypeToText($field, $value);
    
    // Build delimited content
    $content .= "|||{$fieldHandle}|||\n{$textValue}\n";
    
    // Track metadata
    $metadata['fields'][$fieldHandle] = [
        'weight'    => $weight,
        'type'      => $field->fieldType->handle,
        'length'    => strlen($textValue),
    ];
}

return compact('content', 'metadata');
```

#### Step 4: Store Index
```php
// SearchIndexService::persistIndex()
SearchEntryIndex::updateOrCreate(
    ['entry_id' => $entry->id],
    [
        'entry_group_id'  => $entry->entry_group_id,
        'content'         => $content,
        'title_snippet'   => substr($entry->title, 0, 255),
        'search_metadata' => $metadata,
        'indexed_at'      => now(),
    ]
);
```

### C. Reindexing Strategies

#### Strategy 1: Immediate (Small Datasets)
```php
// In SearchEntryObserver
public function created(Entry $entry)
{
    SearchIndexService::indexEntry($entry); // Synchronous
}
```
**Pros**: Instant search availability
**Cons**: Slows down entry creation for large indexes

#### Strategy 2: Queued (Recommended)
```php
// In SearchEntryObserver
public function created(Entry $entry)
{
    SearchReindexEntryGroup::dispatch($entry)
        ->onQueue('search')
        ->delay(now()->addSeconds(5)); // Batch window
}
```
**Pros**: Non-blocking, can batch multiple entries
**Cons**: 5–10s delay before searchable

#### Strategy 3: Bulk Reindex (On Layout Change)
```php
// In SearchConfig observer
public function updated(SearchConfig $config)
{
    // Rebuild entire group's index
    SearchReindexEntryGroup::dispatch($config->entryGroup)
        ->onQueue('search');
}
```

---

## Part 4: API Design & Query Interface

### A. SearchService Facade

```php
// Simple entry search
Search::entries()
    ->in('blog')              // EntryGroup handle
    ->keyword('laravel')
    ->published()
    ->take(20)
    ->get();

// SearchCollection search
Search::collection('site-search')
    ->keyword('setup guide')
    ->limit(50)
    ->get();

// Advanced query
Search::entries()
    ->inGroup('products')
    ->keyword('cpu')
    ->withStatus('published')
    ->andStatus('featured')
    ->orderBy('relevance', 'desc')
    ->paginate(15);

// User search
Search::members()
    ->keyword('john')
    ->role('admin')
    ->get();

// Cross-model search
Search::all(['entries', 'members'])
    ->keyword('integration')
    ->get();
```

### B. SearchQueryBuilder Class Map

```php
namespace App\Builders;

class SearchQueryBuilder
{
    protected array $filters = [];
    protected ?string $keyword = null;
    protected string $searchType = 'entries'; // or 'members' or 'all'
    protected ?string $groupHandle = null;
    protected ?string $collectionHandle = null;
    protected string $orderBy = 'relevance';
    protected string $direction = 'desc';
    protected int $limit = 20;
    protected int $page = 1;
    protected array $scopes = [];
    protected SearchWeightingService $weighting;
    
    // Setters (fluent)
    public function in(string $groupHandle): static
    public function inGroup(string|int $group): static
    public function inCollection(string|int $collection): static
    public function keyword(string $keyword): static
    public function published(): static
    public function withStatus(string $handle): static
    public function andStatus(string $handle): static
    public function orderBy(string $column, string $dir = 'asc'): static
    public function limit(int $limit): static
    public function take(int $limit): static  // alias
    public function offset(int $offset): static
    public function page(int $page, int $perPage = 20): static
    public function where(string $column, $operator, $value = null): static
    
    // Getters
    public function get(): Collection
    public function first(): ?Entry
    public function paginate(int $perPage = 20): LengthAwarePaginator
    public function count(): int
    public function toSql(): string  // Debug
}
```

### C. SearchEntryService Implementation

```php
namespace App\Services;

class SearchEntryService
{
    public function __construct(
        private SearchIndexRepository $indexRepo,
        private SearchWeightingService $weighting,
    ) {}
    
    public function search(SearchQueryBuilder $builder): Collection|LengthAwarePaginator
    {
        $query = $this->buildQuery($builder);
        
        // Apply pagination or collection
        if ($builder->hasPagination()) {
            return $query->paginate($builder->getPerPage());
        }
        
        return $query->get();
    }
    
    private function buildQuery(SearchQueryBuilder $builder): Builder
    {
        $query = SearchEntryIndex::query()
            ->with($this->eagerLoad());
        
        // Group filter
        if ($groupHandle = $builder->getGroupHandle()) {
            $query->whereHas('entryGroup', fn($q) => 
                $q->where('handle', $groupHandle)
            );
        }
        
        // SearchCollection filter
        if ($collectionHandle = $builder->getCollectionHandle()) {
            $query->whereHas('entry.searchCollections', fn($q) =>
                $q->where('search_collections.handle', $collectionHandle)
            );
        }
        
        // Keyword search (FULLTEXT)
        if ($keyword = $builder->getKeyword()) {
            $query->whereRaw(
                "MATCH(content) AGAINST(? IN BOOLEAN MODE)",
                [$this->formatKeyword($keyword)]
            );
        }
        
        // Status filters
        foreach ($builder->getStatuses() as $status) {
            $query->whereHas('entry', fn($q) =>
                $q->where('status_handle', $status)
            );
        }
        
        // Custom filters
        foreach ($builder->getFilters() as $filter) {
            $query->where($filter['column'], $filter['operator'], $filter['value']);
        }
        
        // Ordering
        $order = $builder->getOrderBy();
        if ($order === 'relevance') {
            $query->orderByRaw("MATCH(content) AGAINST(? IN BOOLEAN MODE) DESC", 
                [$this->formatKeyword($builder->getKeyword())]);
        } else {
            $query->orderBy($order, $builder->getDirection());
        }
        
        return $query;
    }
    
    private function formatKeyword(string $keyword): string
    {
        // Convert "laravel testing" to "+laravel +testing"
        // Split by spaces, add +prefix to each term
        $terms = collect(explode(' ', trim($keyword)))
            ->filter()
            ->map(fn($term) => '+' . $this->escapeTerm($term))
            ->join(' ');
        
        return $terms;
    }
    
    private function eagerLoad(): array
    {
        return [
            'entry.entryGroup',
            'entry.entryType',
            'entry.creator',
            'entry.fieldValues.field',
        ];
    }
}
```

### D. SearchWeightingService (Advanced)

```php
namespace App\Services;

class SearchWeightingService
{
    public function __construct(
        private SearchFieldPriorityRepository $priorityRepo,
    ) {}
    
    /**
     * Calculate relevance score for search results
     * 
     * Formula:
     *   score = bm25 × fieldWeight × collectionWeight × freshness × status
     */
    public function scoreResult(
        SearchEntryIndex $indexRecord,
        string $keyword,
        ?SearchCollection $collection = null
    ): float {
        $entry = $indexRecord->entry;
        
        // 1. BM25 baseline
        $bm25 = $this->calculateBM25($indexRecord, $keyword);
        
        // 2. Field weights (from SearchConfig)
        $fieldWeight = $this->getFieldWeightFactor(
            $indexRecord,
            $keyword
        );
        
        // 3. SearchCollection override (if searching within collection)
        $collectionWeight = $this->getCollectionWeightFactor(
            $collection,
            $keyword
        );
        
        // 4. Freshness decay
        $freshness = $this->calculateFreshness(
            $entry,
            $indexRecord->entryGroup->searchConfig
        );
        
        // 5. Status multiplier
        $statusMultiplier = $this->getStatusMultiplier($entry);
        
        return $bm25 * $fieldWeight * $collectionWeight * $freshness * $statusMultiplier;
    }
    
    private function calculateBM25(
        SearchEntryIndex $indexRecord,
        string $keyword,
        float $k1 = 1.2,
        float $b = 0.75
    ): float {
        $metadata = $indexRecord->search_metadata;
        $totalDocs = SearchEntryIndex::count();
        
        $score = 0.0;
        
        foreach (explode(' ', $keyword) as $term) {
            $term = trim($term);
            if (empty($term)) continue;
            
            // Find docs containing term
            $docsWithTerm = SearchEntryIndex::whereRaw(
                "MATCH(content) AGAINST(? IN BOOLEAN MODE)",
                ["+{$term}"]
            )->count();
            
            // IDF calculation
            $idf = log(($totalDocs - $docsWithTerm + 0.5) / ($docsWithTerm + 0.5) + 1);
            
            // TF (approximate from match positions)
            $tf = substr_count($indexRecord->content, $term);
            
            // Field length
            $docLength = strlen($indexRecord->content);
            $avgDocLength = SearchEntryIndex::query()
                ->selectRaw('AVG(CHAR_LENGTH(content)) as avg')
                ->first()
                ->avg;
            
            // BM25 formula
            $numerator = ($k1 + 1) * $tf;
            $denominator = $k1 * (1 - $b + $b * ($docLength / $avgDocLength)) + $tf;
            
            $score += $idf * ($numerator / $denominator);
        }
        
        return $score;
    }
    
    private function getFieldWeightFactor(
        SearchEntryIndex $indexRecord,
        string $keyword
    ): float {
        $metadata = $indexRecord->search_metadata ?? [];
        $fields = $metadata['fields'] ?? [];
        
        $totalScore = 0.0;
        $weightCount = 0;
        
        // For each field that matches keyword
        foreach ($fields as $fieldHandle => $fieldMeta) {
            $weight = $fieldMeta['weight'] ?? 1.0;
            
            // Check if keyword term(s) appear in this field's content
            foreach (explode(' ', $keyword) as $term) {
                if (stripos($fieldHandle, trim($term)) !== false) {
                    $totalScore += $weight;
                    $weightCount++;
                }
            }
        }
        
        return $weightCount > 0 ? ($totalScore / $weightCount) : 1.0;
    }
    
    private function getCollectionWeightFactor(
        ?SearchCollection $collection,
        string $keyword
    ): float {
        if (!$collection) {
            return 1.0;
        }
        
        // Would query SearchCollectionFieldPriority here
        // For now, return 1.0 (can be extended)
        return 1.0;
    }
    
    private function calculateFreshness(
        Entry $entry,
        SearchConfig $config
    ): float {
        if (!$config->apply_freshness_decay) {
            return 1.0;
        }
        
        $daysSincePublish = $entry->published_at
            ->diffInDays(now());
        
        $halfLife = $config->freshness_half_life ?? 30;
        
        // Exponential decay: 1.0 + 0.5 * exp(-t / halfLife)
        $factor = 1.0 + (0.5 * exp(-$daysSincePublish / $halfLife));
        
        return min($factor, 1.5); // Cap at 1.5x boost
    }
    
    private function getStatusMultiplier(Entry $entry): float
    {
        return match($entry->status_handle) {
            'published'  => 1.0,
            'featured'   => 1.5,
            'archived'   => 0.3,
            'draft'      => 0.0,
            default      => 0.5,
        };
    }
}
```

---

## Part 5: Complete Request/Response Cycle

### A. HTTP Request Flow

```
POST /api/v1/search?q=laravel&group=blog

↓

SearchController::search()
  ├─ Validate input
  ├─ Create SearchQueryBuilder
  │  └─ ->inGroup('blog')->keyword('laravel')->published()
  ├─ SearchService::search($builder)
  │  ├─ Build SQL query
  │  ├─ Execute FULLTEXT search
  │  ├─ Load relations
  │  └─ Score with SearchWeightingService
  ├─ Transform to resource
  └─ Return JSON

↓

HTTP 200
{
  "data": [
    {
      "id": 1,
      "title": "Laravel Testing Best Practices",
      "excerpt": "Testing is essential...",
      "group": "blog",
      "relevance": 0.87,
      "published_at": "2024-04-15T10:30:00Z",
      "url": "/blog/laravel-testing"
    },
    ...
  ],
  "meta": {
    "total": 42,
    "total_pages": 3,
    "current_page": 1,
    "per_page": 15
  }
}
```

### B. Search in SearchCollection Context

```
GET /api/v1/search-collections/featured-blog/search?q=php

↓

SearchController::searchInCollection('featured-blog')
  ├─ Resolve SearchCollection + assigned groups
  ├─ SearchQueryBuilder
  │  ├─ ->inCollection('featured-blog')
  │  └─ ->keyword('php')
  ├─ Apply collection-level field priorities
  │  └─ (e.g., 'summary' weight: 2.0 in this collection)
  └─ Return top results

Result:
  - Entry from 'blog' group (assigned to collection)
  - Entry from 'articles' group (also assigned)
  - No entries from 'products' (not assigned)
  - Relevance re-scored with collection weights
```

---

## Part 6: Configuration Example

### A. SearchConfig Setup (Post-Creation)

```php
// After EntryGroup created, set up search
$config = SearchConfig::firstOrCreate(
    ['entry_group_id' => $entryGroup->id],
    [
        'include_title'        => true,
        'include_handle'       => false,
        'apply_freshness_decay'=> true,
        'freshness_half_life'  => 30,
    ]
);

// Enable specific fields for search
$titleField = Field::where('handle', 'title')->first();
$bodyField = Field::where('handle', 'body')->first();
$tagsField = Field::where('handle', 'tags')->first();

// Mark fields as searchable
SearchField::create([
    'search_config_id' => $config->id,
    'field_id'         => $titleField->id,
    'is_enabled'       => true,
]);

SearchField::create([
    'search_config_id' => $config->id,
    'field_id'         => $bodyField->id,
    'is_enabled'       => true,
]);

SearchField::create([
    'search_config_id' => $config->id,
    'field_id'         => $tagsField->id,
    'is_enabled'       => true,
]);

// Set priorities
SearchFieldPriority::create([
    'search_config_id' => $config->id,
    'field_id'         => $titleField->id,
    'weight'           => 2.0,   // Title is most important
]);

SearchFieldPriority::create([
    'search_config_id' => $config->id,
    'field_id'         => $bodyField->id,
    'weight'           => 1.0,   // Body is baseline
]);

SearchFieldPriority::create([
    'search_config_id' => $config->id,
    'field_id'         => $tagsField->id,
    'weight'           => 0.8,   // Tags have lower weight
]);
```

### B. SearchCollection Setup

```php
// Create SearchCollection spanning multiple groups
$collection = SearchCollection::create([
    'name'         => 'Site-wide Search',
    'handle'       => 'site-search',
    'search_type'  => 'entries',
    'description'  => 'Global search across blog and articles',
]);

// Assign groups
SearchCollectionGroup::create([
    'search_collection_id' => $collection->id,
    'entry_group_id'       => $blogGroup->id,
    'sort_order'           => 1,
]);

SearchCollectionGroup::create([
    'search_collection_id' => $collection->id,
    'entry_group_id'       => $articlesGroup->id,
    'sort_order'           => 2,
]);

// Override priorities for this collection
// In 'site-search', emphasize summaries more
SearchCollectionFieldPriority::create([
    'search_collection_id' => $collection->id,
    'field_id'             => $summaryField->id,
    'weight'               => 2.0,  // Higher than group default of 1.5
]);
```

---

## Part 7: Error Handling & Edge Cases

### A. Missing SearchConfig

**Scenario**: User searches in group without SearchConfig

```php
// SearchEntryService
if (!$config = $entry->entryGroup->searchConfig) {
    // Create default config
    $config = SearchConfig::createDefault($entry->entryGroup);
    
    // Log warning
    Log::warning("SearchConfig missing for group {$entry->entryGroup->id}, created default");
}
```

### B. Unindexed Entries

**Scenario**: Entry created before search index migrated

```php
// Command: artisan search:reindex
php artisan search:reindex --group=blog

// Reindexes all entries in group
// Or with --all to reindex everything
```

### C. Concurrent Index Updates

**Race condition**: Two processes update same entry's index

**Solution**: Use database-level UPSERT (MySQL 8.0+)

```php
SearchEntryIndex::upsert(
    [
        ['entry_id' => 1, 'content' => '...', 'indexed_at' => now()],
        ['entry_id' => 2, 'content' => '...', 'indexed_at' => now()],
    ],
    uniqueBy: ['entry_id'],
    update: ['content', 'indexed_at']
);
```

---

## Part 8: Performance Considerations

### A. Indexes to Create

```sql
-- On search_entry_indexes
CREATE FULLTEXT INDEX ft_search_content ON search_entry_indexes (content);
CREATE INDEX idx_entry_group ON search_entry_indexes (entry_group_id);
CREATE INDEX idx_indexed_at ON search_entry_indexes (indexed_at);

-- On field_values
CREATE INDEX idx_field_id_fieldable ON field_values (field_id, fieldable_id, fieldable_type);

-- On searches (if logging/analytics)
CREATE INDEX idx_search_query_date ON searches (query, created_at);
```

### B. Query Optimization

```php
// ✓ Good: with proper eager loading
Search::entries()
    ->keyword('test')
    ->get();  // Loads search_index + entry + relations in ~3 queries

// ✗ Bad: N+1
foreach ($results as $result) {
    $result->entry->entryGroup->name;  // Additional query per row
}

// ✓ Good: use query builder scoping
Search::entries()
    ->keyword('test')
    ->with('entry.entryGroup')
    ->get();
```

### C. Caching Search Results

```php
// Cache common queries
Cache::remember(
    "search:blog:laravel",
    now()->addHours(1),
    fn() => Search::entries()
        ->in('blog')
        ->keyword('laravel')
        ->get()
);

// Invalidate on entry update
SearchEntryObserver::updated($entry) {
    Cache::forget("search:{$entry->entryGroup->handle}:*");
}
```

---

## Summary: Key Files to Create

| File | Purpose |
|------|---------|
| `app/Models/SearchConfig.php` | Configuration per group |
| `app/Models/SearchEntryIndex.php` | Denormalized search index |
| `app/Models/SearchUserIndex.php` | User search index |
| `app/Models/SearchCollection.php` | Grouping of search scopes |
| `app/Models/SearchCollectionGroup.php` | Pivot for SearchCollection ↔ EntryGroup |
| `app/Models/SearchCollectionFieldPriority.php` | Override weights per collection |
| `app/Models/SearchField.php` | Track enabled fields |
| `app/Models/SearchFieldPriority.php` | Field weights per group |
| `app/Services/SearchService.php` | Facade/entry point |
| `app/Services/SearchEntryService.php` | Entry-specific search logic |
| `app/Services/SearchUserService.php` | User-specific search logic |
| `app/Services/SearchIndexService.php` | Index building/maintenance |
| `app/Services/SearchWeightingService.php` | Relevance scoring |
| `app/Services/SearchConfigService.php` | Config management |
| `app/Builders/SearchQueryBuilder.php` | Fluent search API |
| `app/Builders/SearchIndexBuilder.php` | Index content building |
| `app/Observers/SearchEntryObserver.php` | Trigger indexing on entry changes |
| `app/Observers/SearchUserObserver.php` | Trigger indexing on user changes |
| `app/Jobs/SearchReindexEntryGroup.php` | Queued indexing |
| `app/Jobs/SearchReindexUser.php` | Queued user indexing |
| `app/Repositories/SearchIndexRepository.php` | Index queries |
| `app/Repositories/SearchConfigRepository.php` | Config queries |
| `database/migrations/...` | All tables listed in models |

---

**All new objects are now consistently prefixed with `Search`**
