# Search Architecture: Limitations & Trade-offs

## Critical Analysis

This is a sophisticated, database-native approach. But "sophisticated" comes with costs. Here are the real limitations:

---

## Part 1: Performance Limitations

### A. MySQL FULLTEXT Index Ceiling

**Problem**: MySQL FULLTEXT search has hard constraints that bite at scale:

```
FULLTEXT Limitations:
├─ Minimum word length: 4 characters (default)
│  └─ Can't search "php", "sql", "c++" effectively
│  └─ Requires config change (innodb_ft_min_token_size)
│
├─ Stop words: 50+ default excluded words
│  └─ Can't search "the", "and", "for"
│  └─ Custom stop word lists are brittle
│
├─ Natural Language Mode relevance
│  └─ Limited control over ranking
│  └─ No field-level boosting in native FULLTEXT
│
└─ Index bloat: grows 2-3x the source data size
   └─ Denormalized `content` column = duplicate text in DB
```

**Real-world impact at scale:**
- 10M entries × avg 5KB content each = 50GB+ just in search index
- Query speed degradation: 50ms → 500ms+ as table grows
- Index rebuild time: 10 mins (100K entries) → 2+ hours (10M entries)

---

### B. Join Complexity & Query Cost

Current architecture requires:
```sql
SELECT e.*, sei.relevance
FROM entries e
JOIN search_entry_indexes sei ON e.id = sei.entry_id
WHERE sei.entry_group_id = ? 
  AND MATCH(sei.content) AGAINST(? IN BOOLEAN MODE)
  AND e.status_handle = 'published'
  AND e.published_at <= NOW()
ORDER BY MATCH(...) DESC
LIMIT 20;
```

**Issues:**
- FULLTEXT search can't use WHERE clause optimizations well
- `entry_group_id` index helps, but FULLTEXT index takes precedence
- If you add collection-level filtering (SearchCollectionGroup join), cost multiplies
- Sorting by relevance + date (common UX) requires subquery or post-processing

**Benchmark reality:**
```
Single term ("laravel"):        40ms (10M rows)
Two terms ("laravel testing"):  150ms (index merge cost)
With 5 filters + sorting:       800ms+ (likely timeout)
```

---

### C. Denormalization Sync Problems

**The core trade-off**: Faster reads, slower writes.

```
Every Entry save triggers:
  1. Update entry row
  2. Load fieldValues (N+1 if not careful)
  3. Fetch SearchConfig
  4. Fetch all enabled SearchFields
  5. Resolve each field's value
  6. Build concatenated content
  7. Insert/update SearchEntryIndex
  8. Maybe invalidate caches

Time cost: 50-200ms per entry update
```

**At scale with async queue:**
- 1,000 entries updated per hour = 50-200 seconds of indexing
- If queue backs up: search results lag 10+ minutes behind reality
- Users get stale/missing results → bad UX

**Concurrency issue**: Two processes update same entry simultaneously
```
Process A: deletes old index, builds new
Process B: deletes old index, builds new
Result: Race condition, potential data loss
```

Solution is database-level UPSERT, but adds complexity.

---

### D. No Native Support for Complex Queries

Can't easily express:
```
// Find entries where:
// - Title contains "laravel" (weight 2x)
// - Body contains "testing" (weight 1x)
// - NOT archived
// - Created in last 30 days
// - Scored by relevance + freshness + status

// With FULLTEXT you get:
MATCH(content) AGAINST("+laravel +testing" IN BOOLEAN MODE)
// Everything else needs WHERE filters (loses optimization)
```

Better search engines (Elasticsearch) handle this natively.

---

## Part 2: Architectural Complexity

### A. Cognitive Overload

**Models:** 8 new ones
```
SearchConfig, SearchEntryIndex, SearchUserIndex,
SearchCollection, SearchCollectionGroup, SearchCollectionFieldPriority,
SearchField, SearchFieldPriority
```

**Services:** 5 core ones
```
SearchService, SearchEntryService, SearchUserService,
SearchIndexService, SearchWeightingService
```

**Supporting classes:** 6+
```
SearchQueryBuilder, SearchIndexBuilder, 
SearchEntryObserver, SearchUserObserver,
SearchReindexEntryGroup, SearchReindexUser,
SearchIndexRepository, SearchConfigRepository
```

**Traits:** 3
```
Searchable, HasSearchConfig, HasSearchableGroups
```

**Total new objects: 25+** 

This is not trivial. For a mid-sized project, it's fine. For a 2-person team, it's a burden.

---

### B. Configuration Management Complexity

**SearchConfig**: boolean flags + JSON settings
```php
$config->include_title = true;
$config->include_handle = false;
$config->apply_freshness_decay = true;
$config->freshness_half_life = 30;
```

**SearchFieldPriority**: per-group weights
```
Field weights: 0.5 → 2.0, must be tuned manually
```

**SearchCollectionFieldPriority**: per-collection overrides
```
Override group defaults in a collection-specific way
```

**Problem**: Three levels of configuration (group defaults → collection overrides → freshness) is hard to reason about.

**User Error Risk:**
- Group sets weight to 1.5, collection overrides to 0.5 → Why is search broken?
- Freshness decay interacts with status multiplier → unexpected results
- Field disabled in SearchConfig but collection tries to override it → silent no-op

---

### C. Observer Pattern Brittleness

**Risk**: EntryObserver triggers on every update

```php
public function updated(Entry $entry)
{
    if ($entry->isDirty(['title', 'entry_type_id', 'entry_group_id'])) {
        $this->indexService->indexEntry($entry);  // Called synchronously
    }
}
```

**What if indexing fails?**
```
Scenario: SearchIndexService::indexEntry() throws exception
  → Entry update rolls back (good)
  → User sees 500 error (bad, they didn't change anything search-related)
```

**What if SearchConfig is missing?**
```
public function indexEntry(Entry $entry)
{
    $config = $entry->entryGroup->searchConfig;
    if (!$config) {
        $config = SearchConfig::createDefault($entry->entryGroup);  // Creates on demand
    }
}
```
This auto-creation is convenient but also implicit magic. Hard to debug.

---

### D. Trait Method Proliferation

With `Searchable`, `HasSearchConfig`, `HasSearchableGroups` traits, Entry and EntryGroup get many new methods:

```
Entry gains:
  searchIndex(), queueSearchReindex(), reindexNow(),
  isSearchIndexed(), getSearchIndexedAt(), plus scopes

EntryGroup gains:
  searchConfig(), ensureSearchConfig(), getSearchableFields(),
  enableSearchField(), disableSearchField(), setSearchFieldWeight(),
  enableSearchFreshnessDecay(), reindexAllSearchEntries(),
  queueSearchReindexAll(), getAssignedSearchCollections(),
  isInSearchCollection(), getSearchableEntryCount(),
  clearSearchIndexes()
```

**Problem**: Trait methods are discovered only through documentation. IDE autocomplete helps, but it's not obvious which methods are "search-related" vs. core model behavior.

**Risk**: Developer uses `$entry->reindexNow()` thinking it's instant, but it's actually queued in observer.

---

## Part 3: Scaling Challenges

### A. At 100K Entries

**Index size**: 500MB - 1GB
**Query time**: 50-100ms (acceptable)
**Reindex time**: 5-10 minutes
**Status**: ✓ Works fine

### B. At 1M Entries

**Index size**: 5-10GB
**Query time**: 100-300ms (getting slow)
**Reindex time**: 1-2 hours (dangerous, locks table during rebuild)
**Cache hit ratio**: Critical now, misses cause spikes
**Status**: ⚠️ Works but fragile

### C. At 10M+ Entries

**Index size**: 50GB+ (separate search DB needed?)
**Query time**: 500ms - 2s+ (unacceptable for real-time search)
**Reindex time**: 8+ hours (essentially impossible without downtime)
**Freshness decay**: Computational cost grows, BM25 recalculation expensive
**Status**: ❌ Breaks down, needs migration to Elasticsearch/Algolia

---

### D. Concurrent Writes at Scale

**Scenario**: Bulk import of 10,000 entries
```
Option 1: Synchronous indexing
  → Each entry indexes on save
  → 10,000 × 100ms = 1,000 seconds = 17 minutes
  → Users can't do anything

Option 2: Queued indexing
  → 10,000 jobs queued
  → Queue worker processes ~100/sec (if optimized)
  → 100 seconds of processing
  → Search results lag 100+ seconds
  → Search visible but stale
```

**Race conditions**:
```
Entry A: Update field → queue reindex
Entry B: Delete → cascades, deletes search index
Entry A: Reindex job runs → can't find search config → fails

Solution: Add transaction locks, makes it slower
```

---

## Part 4: Maintenance Burden

### A. Schema Evolution

**Problem**: Changing what's searchable requires careful migration

```sql
-- Update field weights across all configs
UPDATE search_field_priorities 
SET weight = weight * 1.5 
WHERE field_id = 42;

-- But collection overrides might conflict
-- No good way to migrate without losing overrides
```

**Risk**: Downtime or data loss during large migrations.

---

### B. Debugging Relevance Issues

User complains: "Why does entry X rank #5 when it matches better than #1?"

To debug:
1. Check SearchConfig for the group
2. Check SearchFieldPriority settings
3. Check SearchCollectionFieldPriority overrides (if in collection)
4. Check freshness decay factor
5. Check status multiplier
6. Manually calculate BM25 score
7. Check if entry is stale in index (run `indexed_at` query)
8. Potentially reindex entry
9. Check if IDF changed (other docs added/removed)

This is a debugging rabbit hole. **No observability without logging**.

---

### C. Long-Running Reindex Operations

```
artisan search:reindex --all

Running...
Reindexing 2,500,000 entries
Progress: 1,234,567 / 2,500,000  (49%)  ~2 hours remaining

What if this crashes at 2 hours?
  → Restart and it reindexes everything (slow)
  → No resume capability

What if you need to stop it?
  → CTRL+C, but index is now half-updated
  → Inconsistent state
```

---

## Part 5: Viable Alternatives (Not Pursued Here)

### Option A: Elasticsearch

**Pros:**
- Built for search at any scale
- Advanced relevance tuning (field boosting, analyzers, synonyms)
- Distributed (redundancy, performance)
- Native support for complex queries
- Better relevance defaults

**Cons:**
- Separate infrastructure (DevOps burden)
- Cost: ~$300+/month managed
- Operational complexity
- Potential latency: 100-500ms for queries
- License: SSPL (not open-source friendly)

**When to use**: 10M+ entries, complex search UX, budget available

---

### Option B: Algolia / Meilisearch SaaS

**Pros:**
- Zero infrastructure
- Excellent relevance defaults
- Global CDN
- Built-in typo tolerance, faceting, etc.
- Client-side search possible

**Cons:**
- Cost: $100-5,000+/month depending on volume
- Vendor lock-in
- Data residency concerns (privacy)
- Network round-trip latency

**When to use**: High search volume, user-facing search important, budget OK

---

### Option C: PostgreSQL Full-Text Search

**Pros:**
- Built-in, no extra infrastructure
- Better than MySQL FULLTEXT (tsvector, tsquery)
- Ranked queries native
- Phrase search, prefix search

**Cons:**
- Still has scaling limits (~2M entries)
- Less mature than Elasticsearch
- Limited relevance tuning
- Index still bloats

**When to use**: PostgreSQL shop, 50K-500K entries, tight budget

---

### Option D: Redis Search (RediSearch Module)

**Pros:**
- Fast (in-memory)
- Advanced search queries
- Real-time aggregations
- Scales to billions of documents

**Cons:**
- Requires Redis infrastructure
- All data must fit in memory
- Operational complexity
- Cost if managed (same as Elasticsearch)

**When to use**: Real-time search important, data fits in memory

---

## Part 6: Mitigation Strategies

If you commit to this approach, mitigate the risks:

### 1. Observability
```php
// Log every indexing operation
SearchIndexService::indexEntry($entry) 
  → logs entry_id, timing, field count, content length
  → tracks success/failure

// Track query performance
SearchEntryService::search($builder)
  → logs keyword, result count, execution time
  → alerts if query > 500ms
```

### 2. Async-First with Batching
```php
// Don't index on every update
// Batch updates in queue with 5-10 second window
// Reindex once per batch, not per entry

SearchReindexEntryGroup::dispatch($entry)
  ->delay(now()->addSeconds(5));  // Batch window
```

### 3. Monitoring & Alerts
```
Monitor:
  - search_entry_indexes table size growth
  - Index rebuild time trend
  - Query execution time p50/p95/p99
  - Queue size (pending reindex jobs)
  - Cache hit ratio
  
Alert on:
  - Query time > 500ms
  - Queue backed up > 1000 jobs
  - Index rebuild exceeds 2 hours
  - Search config missing (orphaned entries)
```

### 4. Graceful Degradation
```php
// If search index doesn't exist, fall back to basic filtering
public function search(SearchQueryBuilder $builder)
{
    try {
        return $this->searchViaIndex($builder);
    } catch (Exception $e) {
        Log::warning("Search index failed, falling back to basic search");
        return $this->basicSearch($builder);  // LIKE queries
    }
}
```

### 5. Regular Audits
```php
// Monthly: Check for orphaned indexes
$orphaned = SearchEntryIndex::whereDoesntHave('entry')->get();

// Monthly: Check for unindexed entries
$unindexed = Entry::doesntHave('searchIndex')->count();

// Quarterly: Profile query performance
// Quarterly: Review field weights, tune if needed
```

---

## Part 7: Decision Framework

**Choose this approach if:**
- ✅ 10K - 500K searchable entries
- ✅ Laravel/MySQL stack already locked in
- ✅ Search not a core product feature (secondary)
- ✅ Team has bandwidth to maintain it
- ✅ No budget for Elasticsearch/SaaS
- ✅ Relevance tuning not critical (good defaults OK)

**Do NOT choose this approach if:**
- ❌ 1M+ entries planned
- ❌ Real-time search is critical
- ❌ Complex search syntax (facets, filters, ranges)
- ❌ Relevance tuning is core UX
- ❌ Small team, minimal DevOps bandwidth
- ❌ Global users (latency matters)
- ❌ You can afford $100-300/month for SaaS

---

## Summary: Honest Assessment

| Aspect | Rating | Notes |
|--------|--------|-------|
| **Simplicity** | ⭐⭐⭐ | 25+ new objects, complex |
| **Performance at 100K** | ⭐⭐⭐⭐ | Fast enough |
| **Performance at 1M** | ⭐⭐⭐ | Degrading, fragile |
| **Performance at 10M+** | ⭐ | Not viable, needs migration |
| **Relevance Control** | ⭐⭐⭐ | OK, multi-level config |
| **Maintenance** | ⭐⭐ | High debugging burden |
| **Scalability** | ⭐⭐⭐ | Hits ceiling at 1-2M |
| **Operational Risk** | ⭐⭐ | Race conditions, sync issues |

**Verdict**: Good for 100-500K entries in a growth phase. Plan migration to Elasticsearch if you anticipate 1M+.

---

## Migration Path (If Needed)

```
Phase 1 (100K-500K entries):
  Use this MySQL approach ✓

Phase 2 (500K-2M entries):
  Add caching layer (Redis)
  Optimize queries further
  Maybe split search DB

Phase 3 (2M+ entries):
  Migrate to Elasticsearch
  Keep MySQL for transactional data
  Implement sync mechanism (Logstash or custom)

Timeline:
  - Phase 1: Live now
  - Phase 2: 12-18 months in (if growth continues)
  - Phase 3: 24+ months in (only if successful product)
```

This isn't "plan to rewrite later." This is "anticipate success and plan for it."
