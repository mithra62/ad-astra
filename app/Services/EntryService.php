<?php

namespace App\Services;

use App\Builders\EntryQueryBuilder;
use App\EntryTypes\EntryTypeRegistry;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryTree;
use App\Models\FieldLayout;
use App\Repositories\EntryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

        return $this->repository->create($entryType, $data);
    }

    /**
     * Update an entry's core attributes, authors, categories, and/or fields.
     * Only keys present in $data are touched.
     *
     * Lifecycle hooks:
     *   The resolved entry type's `beforeUpdate(Entry $entry, array $data): array`
     *   hook runs before any attributes are written, allowing the type to modify
     *   or augment the incoming data. `afterUpdate(Entry $entry, array $data): void`
     *   runs after the entry is saved and refreshed.
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

    // -------------------------------------------------------------------------
    // Schema Resolution
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Entry Tree
    // -------------------------------------------------------------------------

    /**
     * Create a new Entry Tree node and attach it to the given entry.
     *
     * The entry's type must have `has_entry_tree` set to true.
     * Handles are automatically slugified. The home node ($isHome = true) must
     * be a root-level node and can only exist once per tree.
     *
     * @throws InvalidArgumentException if the entry type does not support trees,
     *                                  if placement rules are violated, or if a
     *                                  duplicate handle exists at the same level.
     */
    public function createTreeNode(
        Entry $entry,
        string $handle,
        ?EntryTree $parent = null,
        ?string $template = null,
        bool $isHome = false,
    ): EntryTree {
        return DB::transaction(function () use ($entry, $handle, $parent, $template, $isHome) {
            $entry->loadMissing('entryType');

            if (! $entry->entryType?->has_entry_tree) {
                throw new InvalidArgumentException('This entry type does not support Entry Tree routing.');
            }

            $normalizedHandle = $isHome ? 'home' : EntryTree::validatedHandle($handle);

            $this->treeAssertValidPlacement($parent, $isHome);
            $this->treeAssertUniqueHandleWithinParent($normalizedHandle, $parent);

            $node = EntryTree::create([
                'entry_id'   => $entry->id,
                'parent_id'  => $parent?->id,
                'handle'     => $normalizedHandle,
                'uri'        => '__pending__' . uniqid(),
                'depth'      => $parent ? $parent->depth + 1 : 0,
                'sort_order' => $this->treeNextSortOrder($parent),
                'template'   => $template,
                'is_home'    => $isHome,
            ]);

            $node->uri = $this->treeBuildUri($node);
            $node->save();

            return $node->fresh(['entry.entryType', 'parent']);
        });
    }

    /**
     * Move a tree node to a new parent (or to the root if $newParent is null),
     * placing it at the given $sortOrder among its new siblings.
     *
     * Rebuilds URIs and depth values for the entire moved subtree.
     *
     * @throws InvalidArgumentException on circular-reference or home-node violations.
     */
    public function moveTreeNode(EntryTree $node, ?EntryTree $newParent, int $sortOrder = 0): EntryTree
    {
        return DB::transaction(function () use ($node, $newParent, $sortOrder) {
            $originalParentId = $node->parent_id;

            if ($newParent && $newParent->id === $node->id) {
                throw new InvalidArgumentException('An Entry Tree node cannot be its own parent.');
            }

            if ($newParent && $this->treeIsDescendantOf($newParent, $node)) {
                throw new InvalidArgumentException('An Entry Tree node cannot be moved beneath one of its own children.');
            }

            if ($node->is_home && $newParent) {
                throw new InvalidArgumentException('The Entry Tree home node must remain at the root.');
            }

            $this->treeAssertUniqueHandleInParent($node, $newParent);

            $node->parent_id  = $newParent?->id;
            $node->sort_order = $this->treeNormalizeSortOrder($newParent, $node, $sortOrder);
            $node->setRelation('parent', $newParent);
            $node->save();

            $this->treeRebalanceSiblingSortOrders($originalParentId, $node->id);
            $this->treePlaceNodeAmongSiblings($node);
            $this->rebuildTreeUri($node);

            return $node->fresh(['entry.entryType', 'parent', 'children']);
        });
    }

    /**
     * Recursively rebuild the URI and depth for a node and all of its
     * descendants. Call after any structural change to the tree.
     *
     * @throws InvalidArgumentException if a home node is found below the root.
     */
    public function rebuildTreeUri(EntryTree $node): void
    {
        $node->loadMissing(['parent', 'children']);

        if ($node->is_home && $node->parent_id !== null) {
            throw new InvalidArgumentException('The Entry Tree home node must remain at the root.');
        }

        $node->depth = $node->parent ? $node->parent->depth + 1 : 0;
        $node->uri   = $this->treeBuildUri($node);
        $node->save();

        foreach ($node->children as $child) {
            $this->rebuildTreeUri($child);
        }
    }

    // -- Tree helpers (private) ------------------------------------------------

    private function treeNextSortOrder(?EntryTree $parent): int
    {
        return ((int) EntryTree::query()
            ->where('parent_id', $parent?->id)
            ->max('sort_order')) + 1;
    }

    private function treeBuildUri(EntryTree $node): string
    {
        if ($node->is_home) {
            return '/';
        }

        $segments = [];
        $current  = $node;

        while ($current) {
            if (! $current->is_home) {
                array_unshift($segments, $current->handle);
            }

            $current = $current->parent;
        }

        return implode('/', array_filter($segments)) ?: '/';
    }

    private function treeAssertValidPlacement(?EntryTree $parent, bool $isHome): void
    {
        if (! $isHome) {
            return;
        }

        if ($parent) {
            throw new InvalidArgumentException('The Entry Tree home node must be a root node.');
        }

        if (EntryTree::query()->where('is_home', true)->exists()) {
            throw new InvalidArgumentException('Only one Entry Tree home node may exist.');
        }
    }

    /**
     * Used when creating a new node — checks handle uniqueness by string value.
     */
    private function treeAssertUniqueHandleWithinParent(string $handle, ?EntryTree $parent): void
    {
        $query = EntryTree::query()->where('handle', $handle);

        if ($parent) {
            $query->where('parent_id', $parent->id);
        } else {
            $query->whereNull('parent_id');
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(
                "An Entry Tree node with handle [{$handle}] already exists at this level."
            );
        }
    }

    /**
     * Used when moving an existing node — excludes the node itself from the check.
     */
    private function treeAssertUniqueHandleInParent(EntryTree $node, ?EntryTree $parent): void
    {
        $query = EntryTree::query()
            ->where('handle', $node->handle)
            ->whereKeyNot($node->id);

        if ($parent) {
            $query->where('parent_id', $parent->id);
        } else {
            $query->whereNull('parent_id');
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(
                "An Entry Tree node with handle [{$node->handle}] already exists at this level."
            );
        }
    }

    private function treeIsDescendantOf(EntryTree $possibleChild, EntryTree $possibleParent): bool
    {
        $current = $possibleChild->loadMissing('parent');

        while ($current) {
            if ($current->parent_id === $possibleParent->id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    private function treeNormalizeSortOrder(?EntryTree $parent, EntryTree $node, int $sortOrder): int
    {
        $siblingCount = EntryTree::query()
            ->where('parent_id', $parent?->id)
            ->when($node->exists, fn ($query) => $query->whereKeyNot($node->id))
            ->count();

        return max(1, min($sortOrder, $siblingCount + 1));
    }

    private function treeRebalanceSiblingSortOrders(?int $parentId, ?int $exceptNodeId = null): void
    {
        $siblings = EntryTree::query()
            ->where('parent_id', $parentId)
            ->when($exceptNodeId, fn ($query) => $query->whereKeyNot($exceptNodeId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($siblings as $index => $sibling) {
            $newSortOrder = $index + 1;

            if ($sibling->sort_order !== $newSortOrder) {
                $sibling->forceFill(['sort_order' => $newSortOrder])->save();
            }
        }
    }

    private function treePlaceNodeAmongSiblings(EntryTree $node): void
    {
        $siblings = EntryTree::query()
            ->where('parent_id', $node->parent_id)
            ->whereKeyNot($node->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();

        array_splice($siblings, $node->sort_order - 1, 0, [$node]);

        foreach ($siblings as $index => $sibling) {
            $newSortOrder = $index + 1;

            if ($sibling->sort_order !== $newSortOrder) {
                $sibling->forceFill(['sort_order' => $newSortOrder])->save();
            }
        }
    }
}
