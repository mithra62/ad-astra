<?php

namespace App\Actions\Entry\Tree;

use App\Models\EntryTree;
use App\Services\EntryService;

/**
 * @deprecated Delegate to EntryService::moveTreeNode() directly.
 */
class MoveEntryTreeNode
{
    public function __construct(private readonly EntryService $entryService) {}

    public function handle(EntryTree $node, ?EntryTree $newParent, int $sortOrder = 0): EntryTree
    {
        return $this->entryService->moveTreeNode($node, $newParent, $sortOrder);
    }
}
