<?php

namespace AdAstra\Actions\Entry\Tree;

use AdAstra\Models\EntryTree;
use AdAstra\Services\EntryService;

/**
 * @deprecated Delegate to EntryService::moveTreeNode() directly.
 */
class MoveEntryTreeNode
{
    public function __construct(private readonly EntryService $entryService)
    {
    }

    public function handle(EntryTree $node, ?EntryTree $newParent, int $sortOrder = 0): EntryTree
    {
        return $this->entryService->moveTreeNode($node, $newParent, $sortOrder);
    }
}
