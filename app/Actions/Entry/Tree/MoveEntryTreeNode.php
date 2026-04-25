<?php

namespace App\Actions\Entry\Tree;

use App\Models\EntryTree;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MoveEntryTreeNode
{
    public function __construct(
        protected RebuildEntryTreeUri $rebuildEntryTreeUri
    ) {}

    public function handle(
        EntryTree $node,
        ?EntryTree $newParent,
        int $sortOrder = 0
    ): EntryTree {
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

            $this->assertUniqueHandleWithinParent($node, $newParent);

            $node->parent_id = $newParent?->id;
            $node->sort_order = $this->normalizeSortOrder($newParent, $node, $sortOrder);
            $node->setRelation('parent', $newParent);
            $node->save();

            $this->rebalanceSiblingSortOrders($originalParentId, $node->id);
            $this->placeNodeAmongSiblings($node);
            $this->rebuildEntryTreeUri->handle($node);

            return $node->fresh(['entry.entryType', 'parent', 'children']);
        });
    }

    protected function isDescendantOf(EntryTree $possibleChild, EntryTree $possibleParent): bool
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

    protected function normalizeSortOrder(?EntryTree $parent, EntryTree $node, int $sortOrder): int
    {
        $siblingCount = EntryTree::query()
            ->where('parent_id', $parent?->id)
            ->when($node->exists, fn ($query) => $query->whereKeyNot($node->id))
            ->count();

        return max(1, min($sortOrder, $siblingCount + 1));
    }

    protected function rebalanceSiblingSortOrders(?int $parentId, ?int $exceptNodeId = null): void
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

    protected function placeNodeAmongSiblings(EntryTree $node): void
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

    protected function assertUniqueHandleWithinParent(EntryTree $node, ?EntryTree $parent): void
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
            throw new InvalidArgumentException("An Entry Tree node with handle [{$node->handle}] already exists at this level.");
        }
    }
}
