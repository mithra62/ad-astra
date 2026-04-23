<?php

namespace App\Services;

use App\Builders\EntryQueryBuilder;
use App\EntryTypes\EntryTypeRegistry;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\FieldLayout;
use App\Repositories\EntryRepository;
use Illuminate\Support\Collection;

class EntryService extends AbstractService
{
    public function __construct(
        $app,
        private readonly EntryTypeRegistry $registry,
        private readonly EntryRepository $repository,
    ) {
        parent::__construct($app);
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create an entry of the given type handle.
     *
     * Accepted keys in $data:
     *   title, handle, status, published_at  — core attributes
     *   authors    (array)  — user IDs to sync as authors (keyed by sort order)
     *   categories (array)  — category IDs to sync
     *   fields     (array)  — ['handle' => value] field values (relational or scalar)
     */
    public function create(string $typeHandle, array $data = []): Entry
    {
        $entryType = $this->registry->resolveByHandle($typeHandle);

        return $this->repository->create($entryType, $data);
    }

    /**
     * Update an entry's core attributes, authors, categories, and/or fields.
     * Only keys present in $data are touched.
     */
    public function update(Entry $entry, array $data = []): Entry
    {
        return $this->repository->applyData($entry, $data);
    }

    /**
     * Delete an entry. Field values cascade via DB constraint.
     */
    public function delete(Entry $entry): bool
    {
        return $this->repository->delete($entry);
    }

    /**
     * Fetch an entry by ID with standard eager-loads. Throws ModelNotFoundException if missing.
     */
    public function get(int $id): Entry
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Fetch an entry by ID with standard eager-loads. Returns null if missing.
     */
    public function find(int $id): ?Entry
    {
        return $this->repository->find($id);
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
     * Recursively load related entries for a given relationship field handle,
     * stopping at $maxDepth levels or when a previously-seen entry ID is
     * encountered (cycle detection). Returns a flat Collection of Entry models
     * in traversal order, deduplicated by ID.
     *
     * @param  array<int>  $seen  IDs already visited — managed internally, not by callers
     */
    public function loadRelatedRecursive(
        Entry $entry,
        string $fieldHandle,
        int $maxDepth = 3,
        array $seen = [],
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

    /**
     * Return a query builder scoped to entries.
     */
    public function query(): EntryQueryBuilder
    {
        return new EntryQueryBuilder($this->repository);
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

    // -------------------------------------------------------------------------
    // Schema Resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the effective FieldLayout for an entry.
     * Returns the entry type's layout if assigned, otherwise the group's layout.
     */
    public function resolveLayout(Entry $entry): ?FieldLayout
    {
        $entry->loadMissing('entryType.fieldLayout', 'entryGroup.fieldLayout');

        return $entry->entryType?->fieldLayout ?? $entry->entryGroup?->fieldLayout;
    }

    /**
     * Resolve all Field models available to an entry, merged from both the type
     * and group layouts, deduplicated by field ID.
     */
    public function resolveFields(Entry $entry): Collection
    {
        return $this->repository->resolveLayoutFields($entry);
    }
}
