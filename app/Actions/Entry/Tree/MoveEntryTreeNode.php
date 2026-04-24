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
            if ($newParent && $newParent->id === $node->id) {
                throw new InvalidArgumentException('An Entry Tree node cannot be its own parent.');
            }

            if ($newParent && $this->isDescendantOf($newParent, $node)) {
                throw new InvalidArgumentException('An Entry Tree node cannot be moved beneath one of its own children.');
            }

            $node->parent_id = $newParent?->id;
            $node->sort_order = $sortOrder;
            $node->save();

            $this->rebuildEntryTreeUri->handle($node);

            return $node->fresh(['entry.type', 'parent', 'children']);
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
}
