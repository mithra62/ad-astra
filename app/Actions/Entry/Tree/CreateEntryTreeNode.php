<?php

namespace App\Actions\Entry\Tree;

use App\Actions\AbstractAction;
use App\Models\Entry;
use App\Models\EntryTree;
use App\Services\EntryService;

/**
 * @deprecated Delegate to EntryService::createTreeNode() directly.
 */
class CreateEntryTreeNode extends AbstractAction
{
    public function __construct(private readonly EntryService $entryService)
    {
    }

    public function create(Entry $entry, string $handle, ?EntryTree $parent = null, ?string $template = null, bool $isHome = false): EntryTree
    {
        return $this->entryService->createTreeNode($entry, $handle, $parent, $template, $isHome);
    }
}
