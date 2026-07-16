<?php

namespace AdAstra\Services;

use AdAstra\Builders\EntryQueryBuilder;
use AdAstra\EntryTypes\EntryTypeRegistry;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryMetric;
use AdAstra\Models\EntryTree;
use AdAstra\Models\FieldLayout;
use AdAstra\Repositories\EntryRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntryService extends AbstractService
{
    public function __construct(
        $app,
        private readonly EntryTypeRegistry $registry,
        private readonly EntryRepository $repository,
        private readonly EntryTreeService $trees,
    ) {
        parent::__construct($app);
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new Entry of the given type handle.
     *
     * Accepted keys in $data:
     *   title, handle, status, published_at  — core attributes
     *   authors    (array)  — user IDs to sync as authors (keyed by sort order)
     *   categories (array)  — category IDs to sync
     *   fields     (array)  — ['handle' => value] field values (relational or scalar)
     *
     * Entry Tree keys (only honored when the type has has_entry_tree):
     *   parent_entry_id (int|null)  — ID of the parent *entry* (not tree node);
     *                                 the parent must already have a tree node
     *   template        (string|null)
     *   is_home         (bool)      — last write wins: any existing home node is demoted
     *   redirect_url    (string|null)
     *   redirect_status (int|null)  — defaults to 302 when null
     *
     * Validation:
     *   The entry type's `validate()` method is called before the repository is
     *   touched. If it returns a non-empty error array a ValidationException is
     *   thrown, which the framework converts to a 422 response on HTTP requests.
     *
     * Lifecycle hooks:
     *   The resolved entry type's `beforeCreate(array $data): array` hook runs
     *   inside the database transaction so any locks it acquires (e.g. for
     *   auto-incrementing sequence fields) are held until the row is committed.
     *   `afterCreate(Entry $entry, array $data): void` runs after the transaction
     *   commits, so its side effects (emails, webhooks, etc.) are not rolled back
     *   if persistence fails.
     */
    public function create(string $typeHandle, array $data = []): Entry
    {
        $entryType = $this->registry->resolveByHandle($typeHandle);

        $errors = $entryType->validate($data);
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $entry = $this->repository->create($entryType, $data);

        if ($entryType->getRecord()->has_entry_tree && filled($entry->handle)) {
            $this->trees->createFromData($entry, $data);
        }

        return $entry;
    }

    /**
     * Update an entry's core attributes, authors, categories, and/or fields.
     * Only keys present in $data are touched.
     *
     * Entry Tree keys (parent_entry_id, template, is_home, redirect_url,
     * redirect_status) are honored when the type has has_entry_tree — see
     * EntryTreeService::syncForEntry() for the semantics.
     *
     * Validation:
     *   The entry type's `validate()` method is called before any writes. If it
     *   returns a non-empty error array a ValidationException is thrown, which the
     *   framework converts to a 422 response on HTTP requests.
     *
     * Lifecycle hooks:
     *   The resolved entry type's `beforeUpdate(Entry $entry, array $data): array`
     *   hook runs before any attributes are written, allowing the type to modify
     *   or augment the incoming data. `afterUpdate(Entry $entry, array $data): void`
     *   runs after the entry is saved and refreshed.
     */
    public function update(Entry $entry, array $data = []): Entry
    {
        $entry->loadMissing('entryType');
        $entryType = $this->registry->resolveByRecord($entry->entryType);

        $errors = $entryType->validate($data, $entry);
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // Wrap applyData + syncForEntry in one transaction so a tree sync failure
        // cannot leave the entry data committed without its tree counterpart.
        // Laravel uses savepoints for the nested transaction inside applyData —
        // no changes needed there. afterUpdate (called inside applyData, outside
        // its inner transaction) will run within this outer transaction.
        return DB::transaction(function () use ($entry, $data) {
            // Captured before applyData rewrites it — syncForEntry needs the
            // pre-save handle to tell a tracking tree handle from a custom one.
            $previousHandle = (string) $entry->getOriginal('handle');

            $entry = $this->repository->applyData($entry, $data);

            // applyData calls refresh() which clears loaded relations — reload before tree sync.
            $entry->loadMissing('entryType');
            if ($entry->entryType->has_entry_tree) {
                $entry->loadMissing('entryTree');
                $this->trees->syncForEntry($entry, $data, $previousHandle);
            }

            return $entry;
        });
    }

    /**
     * Delete an entry. Field values cascade via DB constraint.
     */
    public function delete(Entry $entry): bool
    {
        return $this->repository->delete($entry);
    }

    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /**
     * Fetch an entry by ID with standard eager-loads. Returns null if missing.
     */
    public function find(int $id): ?Entry
    {
        return $this->repository->find($id);
    }

    /**
     * Fetch an entry by ID with standard eager-loads. Throws ModelNotFoundException if missing.
     */
    public function get(int $id): Entry
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Fetch an entry by ID with lightweight eager-loads (entryGroup, entryType, creator only).
     * Use in list views, dashboards, or anywhere field values are not needed.
     */
    public function findMeta(int $id): ?Entry
    {
        return $this->repository->findMeta($id);
    }

    /**
     * Fetch an entry by ID with lightweight eager-loads. Throws ModelNotFoundException if missing.
     */
    public function getMeta(int $id): Entry
    {
        return $this->repository->findMetaOrFail($id);
    }

    /**
     * Fetch an entry by its handle within a specific group. Returns null if not found.
     * Always scope by group — the same handle can exist in multiple groups.
     */
    public function findByHandle(string $handle, string|int|EntryGroup $group): ?Entry
    {
        return $this->repository->findByHandle($handle, $group);
    }

    /**
     * Fetch an entry by its handle within a specific group. Throws ModelNotFoundException if not found.
     */
    public function findOrFailByHandle(string $handle, string|int|EntryGroup $group): Entry
    {
        return $this->repository->findOrFailByHandle($handle, $group);
    }

    /**
     * Return a query builder scoped to entries.
     */
    public function query(): EntryQueryBuilder
    {
        return new EntryQueryBuilder();
    }

    // -------------------------------------------------------------------------
    // Custom Fields (Fieldable)
    // -------------------------------------------------------------------------

    /**
     * Return the entry's current field values as ['handle' => resolvedValue].
     */
    public function fieldArray(Entry $entry): array
    {
        $entry->loadMissing('fieldValues.field');

        return $entry->fieldArray();
    }

    /**
     * Read a single field value by handle, ensuring all required relations are
     * loaded before delegating to Entry::field().
     *
     * Returns the resolved scalar value for scalar field types, a Collection of
     * related Entry models for relational field types, or null when no value is
     * stored for that handle.
     */
    public function getFieldValue(Entry $entry, string $fieldHandle): mixed
    {
        $entry->loadMissing([
            'fieldValues.field.fieldType',
            'entryRelationships.field',
            'entryRelationships.relatedEntry',
        ]);

        return $entry->field($fieldHandle);
    }

    /**
     * Persist a single field value on an entry.
     *
     * Routes to scalar (FieldValue) or relational (EntryRelationship) storage
     * automatically based on the field type. Silently skips the handle when it
     * is not present in the entry's resolved layout.
     */
    public function setFieldValue(Entry $entry, string $fieldHandle, mixed $value): void
    {
        $this->repository->setFieldValue($entry, $fieldHandle, $value);
    }

    /**
     * Resolve the effective FieldLayout for an entry.
     * Returns the entry type's layout if assigned, otherwise the group's layout.
     *
     * Guarantees that fieldLayout relations are loaded before delegating to
     * Entry::getFieldLayout(), which owns the precedence logic.
     */
    public function resolveLayout(Entry $entry): ?FieldLayout
    {
        $entry->loadMissing('entryType.fieldLayout', 'entryGroup.fieldLayout');

        return $entry->getFieldLayout();
    }

    /**
     * Resolve all Field models available to an entry, merged from both the type
     * and group layouts, deduplicated by field ID.
     */
    public function resolveFields(Entry $entry): Collection
    {
        return $this->repository->resolveLayoutFields($entry);
    }

    /**
     * Recursively load related entries for a given relationship field handle,
     * stopping at $maxDepth levels or when a previously-seen entry ID is
     * encountered (cycle detection). Returns a flat Collection of Entry models
     * in traversal order, deduplicated by ID.
     *
     * @param array<int> $seen IDs already visited — managed internally, not by callers
     */
    public function loadRelatedRecursive(
        Entry  $entry,
        string $fieldHandle,
        int    $maxDepth = 3,
        array  $seen = [],
    ): Collection {
        if ($maxDepth <= 0 || in_array($entry->id, $seen, true)) {
            return collect();
        }

        $seen[] = $entry->id;
        $entry->loadMissing('entryRelationships.relatedEntry', 'entryRelationships.field');

        $related = $entry->entryRelationships
            ->filter(fn ($r) => $r->field?->handle === $fieldHandle && $r->relatedEntry !== null)
            ->sortBy('sort_order')
            ->pluck('relatedEntry');

        $results = collect();

        foreach ($related as $relatedEntry) {
            if (in_array($relatedEntry->id, $seen, true)) {
                continue;
            }

            $results->push($relatedEntry);
            $results = $results->merge(
                $this->loadRelatedRecursive($relatedEntry, $fieldHandle, $maxDepth - 1, $seen)
            );

            $seen[] = $relatedEntry->id;
        }

        return $results->unique('id')->values();
    }

    // -------------------------------------------------------------------------
    // Entry Tree (delegates to EntryTreeService)
    // -------------------------------------------------------------------------

    /**
     * Create an Entry Tree node for an entry. @see EntryTreeService::createTreeNode()
     */
    public function createTreeNode(
        Entry      $entry,
        string     $handle,
        ?EntryTree $parent = null,
        ?string    $template = null,
        bool       $isHome = false,
        ?string    $redirectUrl = null,
        ?int       $redirectStatus = null,
    ): EntryTree {
        return $this->trees->createTreeNode($entry, $handle, $parent, $template, $isHome, $redirectUrl, $redirectStatus);
    }

    /**
     * Move a tree node to a new parent. @see EntryTreeService::moveTreeNode()
     */
    public function moveTreeNode(EntryTree $node, ?EntryTree $newParent, int $sortOrder = 0): EntryTree
    {
        return $this->trees->moveTreeNode($node, $newParent, $sortOrder);
    }

    /**
     * Rebuild URI and depth for a node and its descendants. @see EntryTreeService::rebuildTreeUri()
     */
    public function rebuildTreeUri(EntryTree $node): void
    {
        $this->trees->rebuildTreeUri($node);
    }

    /**
     * Delete an Entry Tree node. @see EntryTreeService::deleteTreeNode()
     */
    public function deleteTreeNode(EntryTree $node): bool
    {
        return $this->trees->deleteTreeNode($node);
    }

    // -------------------------------------------------------------------------
    // Metrics
    // -------------------------------------------------------------------------

    /**
     * Record (or increment) a named metric for an entry on the given date.
     *
     * When a row already exists for (entry, metric, date) the $value is added to
     * the existing total — repeated calls accumulate. Pass a custom $date to
     * backfill historical data; defaults to today.
     *
     * Race conditions are handled with a single retry on unique-constraint
     * violation: if two processes both see no existing row and race to INSERT,
     * the loser retries as an UPDATE increment.
     *
     * @param int $value Amount to add (defaults to 1).
     * @param Carbon|null $date Date to record against; defaults to today.
     */
    public function recordMetric(Entry $entry, string $metric, int $value = 1, ?Carbon $date = null): EntryMetric
    {
        $recordedDate = ($date ?? today())->toDateString();

        EntryMetric::upsert(
            [['entry_id' => $entry->id, 'metric' => $metric, 'value' => $value, 'recorded_date' => $recordedDate]],
            ['entry_id', 'metric', 'recorded_date'],
            ['value' => DB::raw('value + ' . (int)$value)],
        );

        return EntryMetric::where('entry_id', $entry->id)
            ->where('metric', $metric)
            ->whereDate('recorded_date', $recordedDate)
            ->firstOrFail();
    }
}
