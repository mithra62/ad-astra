<?php

namespace AdAstra\Traits;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryTree;
use Illuminate\Support\Collection;

trait HasEntryTree
{
    /**
     * Return the fully-qualified public URL for this entry's tree node, or null
     * if the entry has no tree node (i.e. the type does not use Entry Tree routing,
     * or the node has not been created yet).
     *
     * Loads the entryTree relation on demand so it is safe to call on any entry
     * instance regardless of how it was fetched — no prior eager-load required.
     *
     * Examples:
     *   $entry->treeUrl()  // "https://example.com/about/team"
     *   $entry->treeUrl()  // null  (non-tree entry or missing node)
     */
    public function treeUrl(): ?string
    {
        $this->loadMissing('entryTree');

        if (!$this->entryTree) {
            return null;
        }

        return url($this->entryTree->url);
    }

    /**
     * Return the parent Entry of this entry in the tree, or null if this entry
     * is a root node or has no tree node at all.
     *
     * Example:
     *   $childEntry->treeParent()  // Entry (the parent page)
     *   $rootEntry->treeParent()   // null
     */
    public function treeParent(): ?Entry
    {
        $this->loadMissing('entryTree.parent.entry');

        return $this->entryTree?->parent?->entry;
    }

    /**
     * Return all direct child Entries of this entry in the tree, ordered by
     * sort_order. Returns an empty Collection if this entry has no children or
     * no tree node.
     *
     * Example:
     *   $parentEntry->treeChildren()  // Collection of Entry models
     */
    public function treeChildren(): Collection
    {
        $this->loadMissing('entryTree.children.entry');

        if (!$this->entryTree) {
            return collect();
        }

        return $this->entryTree->children
            ->map(fn(EntryTree $node) => $node->entry)
            ->filter()
            ->values();
    }

    /**
     * Return all ancestors of this entry ordered from the root down to the
     * immediate parent. Returns an empty Collection for root nodes or entries
     * with no tree node.
     *
     * The walk is iterative (not recursive) and issues one query per level, so
     * performance is proportional to the depth of the tree.
     *
     * Example:
     *   // /blog/2024/my-post  →  [blog, 2024]
     *   $entry->treeAncestors()  // Collection of Entry models, root first
     */
    public function treeAncestors(): Collection
    {
        $this->loadMissing('entryTree');

        if (!$this->entryTree) {
            return collect();
        }

        $ancestors = [];
        $node = $this->entryTree;

        while ($node->parent_id !== null) {
            $node->loadMissing('parent.entry');
            $node = $node->parent;

            if (!$node) {
                break;
            }

            if ($node->entry) {
                array_unshift($ancestors, $node->entry);
            }
        }

        return collect($ancestors);
    }

    /**
     * Return all descendant Entries (children, grandchildren, …) of this entry
     * in a flat Collection ordered by depth then sort_order. The search is
     * bounded by $maxDepth levels below this entry to prevent runaway queries
     * on deep or cyclically corrupt trees.
     *
     * Returns an empty Collection if this entry has no children or no tree node.
     *
     * Example:
     *   $entry->treeDescendants()     // all descendants up to 10 levels deep
     *   $entry->treeDescendants(2)    // only children and grandchildren
     */
    public function treeDescendants(int $maxDepth = 10): Collection
    {
        $this->loadMissing('entryTree');

        if (!$this->entryTree || $maxDepth < 1) {
            return collect();
        }

        return $this->collectTreeDescendants($this->entryTree, $maxDepth);
    }

    /**
     * Recursively collect descendant entries starting from $node up to
     * $remaining levels deep.
     */
    private function collectTreeDescendants(EntryTree $node, int $remaining): Collection
    {
        $node->loadMissing('children.entry');

        $results = collect();

        foreach ($node->children as $child) {
            if ($child->entry) {
                $results->push($child->entry);
            }

            if ($remaining > 1) {
                $results = $results->merge(
                    $this->collectTreeDescendants($child, $remaining - 1)
                );
            }
        }

        return $results;
    }
}
