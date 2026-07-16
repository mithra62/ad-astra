<?php

namespace AdAstra\Services;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Owns all Entry Tree write behavior: node CRUD, URI/depth rebuilding,
 * sibling sort ordering, home-node governance, and the sync between an
 * entry's data payload and its tree node.
 *
 * Reached three ways:
 *   - directly (constructor injection or the container) for pure tree work
 *   - via EntryService::create()/update(), which delegate the Entry Tree keys
 *     of their data payloads to createFromData()/syncForEntry()
 *   - via the EntryService delegators (and the Entries facade), kept for
 *     backward compatibility
 *
 * Exception contract: the entry data path (syncForEntry) throws
 * ValidationException so HTTP requests surface a 422; the direct node API
 * (createTreeNode/moveTreeNode/rebuildTreeUri) throws InvalidArgumentException
 * because a violation there is a programmer error.
 */
class EntryTreeService extends AbstractService
{
    // -------------------------------------------------------------------------
    // Node CRUD
    // -------------------------------------------------------------------------

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

            $this->assertValidPlacement($parent, $isHome);
            $this->assertUniqueHandleWithinParent($normalizedHandle, $parent);

            $provisional = new EntryTree([
                'handle' => $normalizedHandle,
                'is_home' => $isHome,
            ]);
            $provisional->setRelation('parent', $parent);

            $node = EntryTree::create([
                'entry_id' => $entry->id,
                'parent_id' => $parent?->id,
                'handle' => $normalizedHandle,
                'uri' => $this->buildUri($provisional),
                'depth' => $parent ? $parent->depth + 1 : 0,
                'sort_order' => $this->nextSortOrder($parent),
                'template' => $template,
                'redirect_url' => $redirectUrl,
                'redirect_status' => $redirectStatus ?? 302,
                'is_home' => $isHome,
            ]);

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

            if ($newParent && $this->isDescendantOf($newParent, $node)) {
                throw new InvalidArgumentException('An Entry Tree node cannot be moved beneath one of its own children.');
            }

            if ($node->is_home && $newParent) {
                throw new InvalidArgumentException('The Entry Tree home node must remain at the root.');
            }

            $this->assertUniqueHandleInParent($node, $newParent);

            $node->parent_id = $newParent?->id;
            $node->sort_order = $this->normalizeSortOrder($newParent, $node, $sortOrder);
            $node->setRelation('parent', $newParent);
            $node->save();

            $this->rebalanceSiblingSortOrders($originalParentId, $node->id);
            $this->placeNodeAmongSiblings($node);
            $this->rebuildTreeUri($node);

            return $node->fresh(['entry.entryType', 'parent', 'children']);
        });
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
        $node->uri = $this->buildUri($node);
        $node->save();

        foreach ($node->children as $child) {
            $this->rebuildTreeUri($child);
        }
    }

    // -------------------------------------------------------------------------
    // Entry data-path sync (called by EntryService)
    // -------------------------------------------------------------------------

    /**
     * Create a tree node for an entry from the Entry Tree keys of a
     * create()/update() data payload (parent_entry_id, template, is_home,
     * redirect_url, redirect_status). Shared by EntryService::create() and
     * the first-save path of syncForEntry().
     */
    public function createFromData(Entry $entry, array $data): EntryTree
    {
        // The `boolean` validation rule accepts "0"/"1" strings — normalize
        // before use, since (bool)"0" would be true.
        $isHome = filter_var($data['is_home'] ?? false, FILTER_VALIDATE_BOOL);

        // Last write wins: taking the home flag demotes whichever node holds it.
        if ($isHome) {
            $this->demoteExistingHomeNode();
        }

        return $this->createTreeNode(
            entry: $entry,
            handle: $entry->handle,
            parent: $this->resolveParentNode($data['parent_entry_id'] ?? null),
            template: $data['template'] ?? null,
            isHome: $isHome,
            redirectUrl: $data['redirect_url'] ?? null,
            redirectStatus: $data['redirect_status'] ?? null,
        );
    }

    /**
     * Synchronise the Entry Tree node for an existing entry after an update.
     *
     * Mutations handled:
     *   - Handle changed   → update node handle + rebuild URI for the whole subtree,
     *                        but only when the node was tracking the entry's handle.
     *                        Custom tree handles (createTreeNode() accepts any handle)
     *                        are preserved so a save never silently changes a URL.
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
     * @param string|null $previousEntryHandle the entry's handle before this save,
     *                                         used to tell a tracking tree handle
     *                                         from a custom one
     *
     * @throws ValidationException on home-placement or handle-collision violations.
     */
    public function syncForEntry(Entry $entry, array $data, ?string $previousEntryHandle = null): void
    {
        $node = $entry->entryTree;

        if (!$node) {
            if (!filled($entry->handle)) {
                return;
            }
            $this->createFromData($entry, $data);
            return;
        }

        // Resolve the intended final state up front so validation can consider
        // a parent move and a home promotion submitted in the same request.
        $parentKeyPresent = array_key_exists('parent_entry_id', $data);
        $newParentNode = $parentKeyPresent ? $this->resolveParentNode($data['parent_entry_id'] ?? null) : null;
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

        // Sync tree handle: home nodes always use the literal 'home' handle,
        // and demotions restore the entry-based handle (the pre-promotion
        // handle is gone). Otherwise follow the entry's (potentially renamed)
        // handle only when the node was tracking it before this save — a
        // custom tree handle (createTreeNode() accepts any handle; the
        // sandbox seeder relies on that) is preserved so a save never
        // silently changes the node's public URL.
        $targetHandle = $finalIsHome ? 'home' : EntryTree::normalizeHandle($entry->handle);
        $shouldSyncHandle = $finalIsHome
            || $demoting
            || $node->handle === EntryTree::normalizeHandle((string) ($previousEntryHandle ?? $entry->handle));
        if ($shouldSyncHandle && $targetHandle !== '' && $node->handle !== $targetHandle) {
            if ($this->handleTaken($targetHandle, $finalParentId, $node->id)) {
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
            $this->moveTreeNode($node->fresh(), $newParentNode, $this->nextSortOrder($newParentNode));
            return;
        }

        // If the handle or home flag changed (no parent move), rebuild URIs for
        // this node and every descendant so their stored `uri` values stay
        // accurate — home nodes contribute no URI segment, regular nodes do.
        if ($handleChanged || $promoting || $demoting) {
            $this->rebuildTreeUri($node->fresh());
        }
    }

    // -------------------------------------------------------------------------
    // Home-node governance
    // -------------------------------------------------------------------------

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
        if ($restored === '' || $this->handleTaken($restored, $current->parent_id, $current->id)) {
            $restored = ($restored === '' ? 'home' : $restored) . '-' . $current->id;
        }

        $current->is_home = false;
        $current->handle = $restored;
        $current->save();

        $this->rebuildTreeUri($current->fresh());
    }

    private function assertValidPlacement(?EntryTree $parent, bool $isHome): void
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

    // -------------------------------------------------------------------------
    // Handle uniqueness
    // -------------------------------------------------------------------------

    /**
     * Single handle-uniqueness query backing both the assert helpers below
     * (InvalidArgumentException, direct node API) and the entry data path
     * (ValidationException, so HTTP requests surface a 422 instead of a 500).
     *
     * A null $parentId scopes the check to root-level nodes.
     */
    private function handleTaken(string $handle, ?int $parentId, ?int $exceptNodeId = null): bool
    {
        return EntryTree::query()
            ->where('handle', $handle)
            ->where('parent_id', $parentId)
            ->when($exceptNodeId, fn ($query) => $query->whereKeyNot($exceptNodeId))
            ->exists();
    }

    /**
     * Used when creating a new node — checks handle uniqueness by string value.
     */
    private function assertUniqueHandleWithinParent(string $handle, ?EntryTree $parent): void
    {
        if ($this->handleTaken($handle, $parent?->id)) {
            throw new InvalidArgumentException(
                "An Entry Tree node with handle [{$handle}] already exists at this level."
            );
        }
    }

    /**
     * Used when moving an existing node — excludes the node itself from the check.
     */
    private function assertUniqueHandleInParent(EntryTree $node, ?EntryTree $parent): void
    {
        if ($this->handleTaken($node->handle, $parent?->id, $node->id)) {
            throw new InvalidArgumentException(
                "An Entry Tree node with handle [{$node->handle}] already exists at this level."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Structure helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the EntryTree node belonging to a parent entry, identified by the
     * parent entry's ID. Returns null when no ID is given or the entry has no
     * tree node yet.
     */
    private function resolveParentNode(?int $parentEntryId): ?EntryTree
    {
        if (!$parentEntryId) {
            return null;
        }

        return Entry::find($parentEntryId)?->entryTree;
    }

    private function buildUri(EntryTree $node): string
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

    private function isDescendantOf(EntryTree $possibleChild, EntryTree $possibleParent): bool
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
    // Sibling sort ordering
    // -------------------------------------------------------------------------

    private function nextSortOrder(?EntryTree $parent): int
    {
        return ((int)EntryTree::query()
                ->where('parent_id', $parent?->id)
                ->max('sort_order')) + 1;
    }

    private function normalizeSortOrder(?EntryTree $parent, EntryTree $node, int $sortOrder): int
    {
        $siblingCount = EntryTree::query()
            ->where('parent_id', $parent?->id)
            ->when($node->exists, fn ($query) => $query->whereKeyNot($node->id))
            ->count();

        return max(1, min($sortOrder, $siblingCount + 1));
    }

    private function rebalanceSiblingSortOrders(?int $parentId, ?int $exceptNodeId = null): void
    {
        $siblings = EntryTree::query()
            ->where('parent_id', $parentId)
            ->when($exceptNodeId, fn ($query) => $query->whereKeyNot($exceptNodeId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->renumberSequentially($siblings);
    }

    private function placeNodeAmongSiblings(EntryTree $node): void
    {
        $siblings = EntryTree::query()
            ->where('parent_id', $node->parent_id)
            ->whereKeyNot($node->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();

        array_splice($siblings, $node->sort_order - 1, 0, [$node]);

        $this->renumberSequentially($siblings);
    }

    /**
     * Persist 1-based sequential sort_order values over an ordered sibling set,
     * writing only the rows whose sort_order actually changes.
     *
     * @param iterable<int, EntryTree> $siblings ordered, sequentially keyed from 0
     */
    private function renumberSequentially(iterable $siblings): void
    {
        foreach ($siblings as $index => $sibling) {
            $newSortOrder = $index + 1;

            if ($sibling->sort_order !== $newSortOrder) {
                $sibling->forceFill(['sort_order' => $newSortOrder])->save();
            }
        }
    }
}
