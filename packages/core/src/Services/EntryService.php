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
     * Update an entry's core attributes, authors, categories, and/or fields.
     * Only keys present in $data are touched.
     *
     * Entry Tree keys (parent_entry_id, template, is_home, redirect_url,
     * redirect_status) are honored when the type has has_entry_tree — see
     * syncTreeNode() for the semantics.
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

        // Wrap applyData + syncTreeNode in one transaction so a tree sync failure
        // cannot leave the entry data committed without its tree counterpart.
        // Laravel uses savepoints for the nested transaction inside applyData —
        // no changes needed there. afterUpdate (called inside applyData, outside
        // its inner transaction) will run within this outer transaction.
        return DB::transaction(function () use ($entry, $data) {
            $entry = $this->repository->applyData($entry, $data);

            // applyData calls refresh() which clears loaded relations — reload before tree sync.
            $entry->loadMissing('entryType');
            if ($entry->entryType->has_entry_tree) {
                $entry->loadMissing('entryTree');
                $this->syncTreeNode($entry, $data);
            }

            return $entry;
        });
    }

    /**
     * Synchronise the Entry Tree node for an existing entry after an update.
     *
     * Mutations handled:
     *   - Handle changed   → update node handle + rebuild URI for the whole subtree.
     *   - Parent changed   → moveTreeNode, appending to the end of the new siblings
     *                        (which rebalances siblings and rebuilds URIs).
     *                        The parent is identified by `parent_entry_id` — the
     *                        parent *entry's* ID, not a tree node ID.
     *   - Home flag        → last write wins: promoting this node demotes whichever
     *                        node currently holds the flag. Promotion requires the
     *                        node to end up at the root (either already there, or
     *                        moved there in the same request).
     *   - Template / redirect pair → direct column updates when the key is present.
     *
     * If no tree node exists yet, one is created (first save after has_entry_tree
     * is enabled on the type, or a missed create).
     *
     * @throws ValidationException on home-placement or handle-collision violations.
     */
    private function syncTreeNode(Entry $entry, array $data): void
    {
        $node = $entry->entryTree;

        if (!$node) {
            if (!filled($entry->handle)) {
                return;
            }
            // The `boolean` validation rule accepts "0"/"1" strings — normalize
            // before use, since (bool)"0" would be true.
            $isHome = filter_var($data['is_home'] ?? false, FILTER_VALIDATE_BOOL);
            if ($isHome) {
                $this->demoteExistingHomeNode();
            }
            $parentNode = $this->resolveTreeParentNode($data['parent_entry_id'] ?? null);
            $this->createTreeNode(
                entry: $entry,
                handle: $entry->handle,
                parent: $parentNode,
                template: $data['template'] ?? null,
                isHome: $isHome,
                redirectUrl: $data['redirect_url'] ?? null,
                redirectStatus: $data['redirect_status'] ?? null,
            );
            return;
        }

        // Resolve the intended final state up front so validation can consider
        // a parent move and a home promotion submitted in the same request.
        $parentKeyPresent = array_key_exists('parent_entry_id', $data);
        $newParentNode = $parentKeyPresent ? $this->resolveTreeParentNode($data['parent_entry_id'] ?? null) : null;
        $finalParentId = $parentKeyPresent ? $newParentNode?->id : $node->parent_id;

        // The `boolean` validation rule accepts "0"/"1" strings — normalize
        // before use, since (bool)"0" would be true.
        $finalIsHome = array_key_exists('is_home', $data)
            ? filter_var($data['is_home'], FILTER_VALIDATE_BOOL)
            : $node->is_home;

        if ($finalIsHome && $finalParentId !== null) {
            throw ValidationException::withMessages([
                'is_home' => 'The home entry must be a top-level entry.',
            ]);
        }

        $promoting = $finalIsHome && !$node->is_home;
        $demoting = !$finalIsHome && $node->is_home;

        // Last write wins: taking the home flag demotes whichever node holds it.
        if ($promoting) {
            $this->demoteExistingHomeNode($node->id);
        }

        $handleChanged = false;
        $dirty = false;

        // Sync tree handle: home nodes always use the literal 'home' handle;
        // otherwise follow the entry's (potentially renamed) handle.
        $targetHandle = $finalIsHome ? 'home' : EntryTree::normalizeHandle($entry->handle);
        if ($targetHandle !== '' && $node->handle !== $targetHandle) {
            if ($this->treeHandleTaken($targetHandle, $finalParentId, $node->id)) {
                throw ValidationException::withMessages([
                    'handle' => "An Entry Tree node with handle [{$targetHandle}] already exists at this level.",
                ]);
            }
            $node->handle = $targetHandle;
            $handleChanged = true;
            $dirty = true;
        }

        if ($promoting || $demoting) {
            $node->is_home = $finalIsHome;
            $dirty = true;
        }

        // Sync template when the caller explicitly included the key.
        if (array_key_exists('template', $data) && $node->template !== $data['template']) {
            $node->template = $data['template'];
            $dirty = true;
        }

        // Sync the redirect pair when the caller explicitly included the keys.
        // A null redirect_url clears it; a null redirect_status resets to 302.
        if (array_key_exists('redirect_url', $data) && $node->redirect_url !== $data['redirect_url']) {
            $node->redirect_url = $data['redirect_url'];
            $dirty = true;
        }
        if (array_key_exists('redirect_status', $data)) {
            $redirectStatus = (int)($data['redirect_status'] ?? 302);
            if ((int)$node->redirect_status !== $redirectStatus) {
                $node->redirect_status = $redirectStatus;
                $dirty = true;
            }
        }

        if ($dirty) {
            $node->save();
        }

        // Sync parent — moveTreeNode rebalances siblings and rebuilds all URIs,
        // so return early to avoid a redundant rebuildTreeUri call below. Moved
        // nodes are appended to the end of their new siblings.
        if ($parentKeyPresent && $node->parent_id !== $newParentNode?->id) {
            $this->moveTreeNode($node->fresh(), $newParentNode, $this->treeNextSortOrder($newParentNode));
            return;
        }

        // If the handle or home flag changed (no parent move), rebuild URIs for
        // this node and every descendant so their stored `uri` values stay
        // accurate — home nodes contribute no URI segment, regular nodes do.
        if ($handleChanged || $promoting || $demoting) {
            $this->rebuildTreeUri($node->fresh());
        }
    }

    /**
     * Demote whichever node currently holds the home flag (last write wins).
     *
     * The demoted node's handle is restored from its entry's handle (falling
     * back to a node-id suffix when that slug is already taken at its level)
     * and its subtree URIs are rebuilt, since home nodes contribute no URI
     * segment but regular nodes do.
     */
    private function demoteExistingHomeNode(?int $exceptNodeId = null): void
    {
        $current = EntryTree::query()
            ->where('is_home', true)
            ->when($exceptNodeId, fn ($query) => $query->whereKeyNot($exceptNodeId))
            ->first();

        if (!$current) {
            return;
        }

        $current->loadMissing('entry');

        $restored = EntryTree::normalizeHandle((string)$current->entry?->handle);
        if ($restored === '' || $this->treeHandleTaken($restored, $current->parent_id, $current->id)) {
            $restored = ($restored === '' ? 'home' : $restored) . '-' . $current->id;
        }

        $current->is_home = false;
        $current->handle = $restored;
        $current->save();

        $this->rebuildTreeUri($current->fresh());
    }

    /**
     * Boolean variant of the tree handle-uniqueness assertions, for HTTP-facing
     * paths that surface a ValidationException (422) instead of a 500.
     */
    private function treeHandleTaken(string $handle, ?int $parentId, ?int $exceptNodeId = null): bool
    {
        return EntryTree::query()
            ->where('handle', $handle)
            ->where('parent_id', $parentId)
            ->when($exceptNodeId, fn ($query) => $query->whereKeyNot($exceptNodeId))
            ->exists();
    }

    /**
     * Resolve the EntryTree node belonging to a parent entry, identified by the
     * parent entry's ID. Returns null when no ID is given or the entry has no
     * tree node yet.
     */
    private function resolveTreeParentNode(?int $parentEntryId): ?EntryTree
    {
        if (!$parentEntryId) {
            return null;
        }

        return Entry::find($parentEntryId)?->entryTree;
    }

    /**
     * Fetch an entry by ID with standard eager-loads. Returns null if missing.
     */
    public function find(int $id): ?Entry
    {
        return $this->repository->find($id);
    }

    /**
     * Create a new Entry Tree node and attach it to the given entry.
     *
     * The entry's type must have `has_entry_tree` set to true.
     * Handles are automatically slugified. The home node ($isHome = true) must
     * be a root-level node and can only exist once per tree.
     *
     * A null $redirectStatus falls back to the column default of 302.
     *
     * @throws InvalidArgumentException if the entry type does not support trees,
     *                                  if placement rules are violated, or if a
     *                                  duplicate handle exists at the same level.
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
        return DB::transaction(function () use ($entry, $handle, $parent, $template, $isHome, $redirectUrl, $redirectStatus) {
            $entry->loadMissing('entryType');

            if (!$entry->entryType?->has_entry_tree) {
                throw new InvalidArgumentException('This entry type does not support Entry Tree routing.');
            }

            $normalizedHandle = $isHome ? 'home' : EntryTree::validatedHandle($handle);

            $this->treeAssertValidPlacement($parent, $isHome);
            $this->treeAssertUniqueHandleWithinParent($normalizedHandle, $parent);

            $provisional = new EntryTree([
                'handle' => $normalizedHandle,
                'is_home' => $isHome,
            ]);
            $provisional->setRelation('parent', $parent);

            $node = EntryTree::create([
                'entry_id' => $entry->id,
                'parent_id' => $parent?->id,
                'handle' => $normalizedHandle,
                'uri' => $this->treeBuildUri($provisional),
                'depth' => $parent ? $parent->depth + 1 : 0,
                'sort_order' => $this->treeNextSortOrder($parent),
                'template' => $template,
                'redirect_url' => $redirectUrl,
                'redirect_status' => $redirectStatus ?? 302,
                'is_home' => $isHome,
            ]);

            return $node->fresh(['entry.entryType', 'parent']);
        });
    }

    private function treeAssertValidPlacement(?EntryTree $parent, bool $isHome): void
    {
        if (!$isHome) {
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
     * Return a query builder scoped to entries.
     */
    public function query(): EntryQueryBuilder
    {
        return new EntryQueryBuilder();
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
            // Normalized because the `boolean` validation rule accepts "0"/"1" strings.
            $isHome = filter_var($data['is_home'] ?? false, FILTER_VALIDATE_BOOL);

            // Last write wins: taking the home flag demotes whichever node holds it.
            if ($isHome) {
                $this->demoteExistingHomeNode();
            }
            $parentNode = $this->resolveTreeParentNode($data['parent_entry_id'] ?? null);
            $this->createTreeNode(
                entry: $entry,
                handle: $entry->handle,
                parent: $parentNode,
                template: $data['template'] ?? null,
                isHome: $isHome,
                redirectUrl: $data['redirect_url'] ?? null,
                redirectStatus: $data['redirect_status'] ?? null,
            );
        }

        return $entry;
    }

    private function treeBuildUri(EntryTree $node): string
    {
        if ($node->is_home) {
            return '/';
        }

        $segments = [];
        $current = $node;

        while ($current) {
            if (!$current->is_home) {
                array_unshift($segments, $current->handle);
            }

            $current = $current->parent;
        }

        return implode('/', array_filter($segments)) ?: '/';
    }

    private function treeNextSortOrder(?EntryTree $parent): int
    {
        return ((int)EntryTree::query()
                ->where('parent_id', $parent?->id)
                ->max('sort_order')) + 1;
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

            $node->parent_id = $newParent?->id;
            $node->sort_order = $this->treeNormalizeSortOrder($newParent, $node, $sortOrder);
            $node->setRelation('parent', $newParent);
            $node->save();

            $this->treeRebalanceSiblingSortOrders($originalParentId, $node->id);
            $this->treePlaceNodeAmongSiblings($node);
            $this->rebuildTreeUri($node);

            return $node->fresh(['entry.entryType', 'parent', 'children']);
        });
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

    // -------------------------------------------------------------------------
    // Custom Fields (Fieldable)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Schema Resolution
    // -------------------------------------------------------------------------

    /**
     * Fetch an entry by ID with standard eager-loads. Throws ModelNotFoundException if missing.
     */
    public function get(int $id): Entry
    {
        return $this->repository->findOrFail($id);
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

    // -------------------------------------------------------------------------
    // Entry Tree
    // -------------------------------------------------------------------------

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
        $node->uri = $this->treeBuildUri($node);
        $node->save();

        foreach ($node->children as $child) {
            $this->rebuildTreeUri($child);
        }
    }

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

    /**
     * Delete an Entry Tree node.
     *
     * Runs inside a transaction so that the EntryTreeObserver's post-delete
     * URI rebuild either fully succeeds or the entire delete is rolled back.
     *
     * After deletion, `nullOnDelete` promotes direct children to root nodes.
     * The observer automatically calls `rebuildTreeUri()` on each promoted child
     * so that their `depth` and `uri` columns reflect their new position.
     */
    public function deleteTreeNode(EntryTree $node): bool
    {
        return DB::transaction(fn () => (bool)$node->delete());
    }

    // -- Tree helpers (private) ------------------------------------------------

    /**
     * Delete an entry. Field values cascade via DB constraint.
     */
    public function delete(Entry $entry): bool
    {
        return $this->repository->delete($entry);
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
}
